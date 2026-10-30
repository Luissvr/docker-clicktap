<?php

namespace App\Support;

use App\Models\Pagina;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class PageTrash
{
    private ?bool $schemaAvailable = null;

    public function available(): bool
    {
        if ($this->schemaAvailable !== null) {
            return $this->schemaAvailable;
        }

        try {
            $source = CatalogConnection::enabled() ? 'supabase' : config('database.default');
            $sourceReady = $source === config('database.default')
                ? Schema::hasColumn('paginas', 'papelera_en')
                : Schema::connection($source)->hasColumn('paginas', 'papelera_en');

            return $this->schemaAvailable = $sourceReady
                && (! CatalogConnection::enabled() || Schema::connection('local')->hasColumn('paginas', 'papelera_en'));
        } catch (Throwable) {
            return $this->schemaAvailable = false;
        }
    }

    public function move(string $pageId): Pagina
    {
        return $this->movePage($pageId, false);
    }

    public function moveDraft(string $pageId): Pagina
    {
        return $this->movePage($pageId, true);
    }

    public function isUnpublishedDraft(Pagina $page): bool
    {
        return $page->papelera_en === null
            && $page->publicacion_estado === 'sin_publicar'
            && $page->publicacion_commit === null
            && $page->publicada_en === null;
    }

    private function movePage(string $pageId, bool $draftOnly): Pagina
    {
        abort_unless(auth()->user()?->activo, 403);
        abort_unless($this->available(), 503, 'La papelera aún no está disponible.');

        $page = CatalogConnection::transaction(function () use ($pageId, $draftOnly): Pagina {
            $page = Pagina::query()->whereKey($pageId)->lockForUpdate()->firstOrFail();
            abort_if($page->papelera_en !== null, 409, 'La página ya está en la papelera.');
            abort_if($draftOnly && ! $this->isUnpublishedDraft($page), 409, 'Solo se pueden eliminar borradores que nunca se han publicado.');
            $wasNeverPublished = $this->isUnpublishedDraft($page);
            $page->forceFill([
                'activo' => false,
                'papelera_en' => now(),
                'publicacion_estado' => $wasNeverPublished ? 'sin_publicar' : ($page->publicacion_estado === 'retirada' ? 'retirada' : 'retirada_pendiente'),
                'actualizada_por' => auth()->id(),
            ])->save();

            return $page;
        });

        if ($page->publicacion_estado === 'retirada_pendiente') {
            app(PublicationQueue::class)->requestWithdrawal($page);
        } elseif ($this->wasNeverPublished($page)) {
            $running = app(PublicationQueue::class)->cancelPendingPublication($page->id);
            $latest = CatalogConnection::remoteReads(fn (): Pagina => Pagina::findOrFail($page->id));
            if ($running || $latest->publicacion_commit !== null || $latest->publicada_en !== null) {
                $page = CatalogConnection::transaction(function () use ($pageId): Pagina {
                    $page = Pagina::query()->whereKey($pageId)->lockForUpdate()->firstOrFail();
                    $page->forceFill(['publicacion_estado' => 'retirada_pendiente'])->save();

                    return $page;
                });
                app(PublicationQueue::class)->requestWithdrawal($page);
            }
        }

        return $page;
    }

    public function restore(string $pageId): Pagina
    {
        abort_unless(auth()->user()?->activo, 403);
        abort_unless($this->available(), 503, 'La papelera aún no está disponible.');

        return CatalogConnection::transaction(function () use ($pageId): Pagina {
            $page = Pagina::query()->whereKey($pageId)->lockForUpdate()->firstOrFail();
            abort_unless($page->papelera_en !== null, 409);
            abort_unless($this->canLeaveTrash($page), 409, 'Espera la retirada pública antes de restaurar.');
            $page->forceFill(['papelera_en' => null, 'actualizada_por' => auth()->id()])->save();

            return $page;
        });
    }

    public function purge(string $pageId): void
    {
        abort_unless($this->available(), 503, 'La papelera aún no está disponible.');
        CatalogConnection::transaction(function () use ($pageId): void {
            $page = Pagina::query()->whereKey($pageId)->lockForUpdate()->firstOrFail();
            abort_unless($page->papelera_en !== null, 409);
            abort_unless($this->canLeaveTrash($page), 409, 'La retirada pública debe estar comprobada antes de eliminar.');
            $page->getConnection()->table('ventas')->where('pagina_id', $pageId)->update([
                'pagina_id' => null,
                'updated_at' => now(),
            ]);
            $page->delete();
        });
    }

    public function canLeaveTrash(Pagina $page): bool
    {
        return $page->publicacion_estado === 'retirada' || $this->wasNeverPublished($page);
    }

    public function coverUrl(Pagina $page): ?string
    {
        $path = $page->logo_path;
        if (! is_string($path) || $path === '') {
            return null;
        }
        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        try {
            $disk = $page->logo_disk ?: config('clicktap.media_disk', 'public');
            if ($disk === 'supabase') {
                $cache = app(LogoCache::class);
                if ($cache->isKnownMissing($page)) {
                    return null;
                }
                $cached = $cache->url($page);
                if ($cached !== null) {
                    return $cached;
                }
            }

            return $disk === 'public'
                ? Storage::disk($disk)->url($path)
                : Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(15));
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    public function backup(Pagina $page): array
    {
        abort_unless(auth()->user()?->activo && $page->papelera_en !== null, 403);
        abort_unless($this->available(), 503, 'La papelera aún no está disponible.');
        $page->load(['enlaces', 'local']);
        $logo = null;
        $path = $page->logo_path;
        if (is_string($path) && $path !== '' && ! preg_match('~^https?://~i', $path)) {
            $disk = $page->logo_disk ?: config('clicktap.media_disk', 'public');
            if ($disk === 'supabase' && app(LogoCache::class)->url($page) !== null) {
                $cached = app(LogoCache::class)->path($page);
                $contents = $cached !== null ? file_get_contents($cached) : null;
            } else {
                $contents = Storage::disk($disk)->exists($path) ? Storage::disk($disk)->get($path) : null;
            }
            if (is_string($contents)) {
                $logo = [
                    'disk' => $disk,
                    'path' => $path,
                    'base64' => base64_encode($contents),
                ];
            }
        }

        return [
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'page' => $page->getAttributes(),
            'local' => $page->local?->getAttributes(),
            'links' => $page->enlaces->map(fn ($link): array => $link->getAttributes())->all(),
            'logo' => $logo,
        ];
    }

    private function wasNeverPublished(Pagina $page): bool
    {
        return $page->publicacion_estado === 'sin_publicar'
            && $page->publicacion_commit === null
            && $page->publicada_en === null;
    }
}
