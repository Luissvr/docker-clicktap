<?php

namespace App\Support;

use App\Models\Pagina;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class LogoCache
{
    private const MAX_BYTES = 20 * 1024 * 1024;

    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function url(Pagina $page): ?string
    {
        if (! $this->cacheKey($page)) {
            return null;
        }

        return route('paginas.cached-logo', [
            'pagina' => $page,
            'version' => $this->version($page),
        ]);
    }

    public function version(Pagina $page): string
    {
        return substr(hash('sha256', $page->logo_disk.'|'.$page->logo_path), 0, 16);
    }

    public function isCached(Pagina $page): bool
    {
        $key = $this->cacheKey($page);

        return $key !== null && Storage::disk('local')->exists($key);
    }

    public function isKnownMissing(Pagina $page): bool
    {
        $key = $this->cacheKey($page);

        return $key !== null && ! $this->isCached($page)
            && app(CatalogSyncState::class)->cache()->has($this->missingKey($key));
    }

    public function path(Pagina $page, bool $retryMissing = false): ?string
    {
        $key = $this->cacheKey($page);
        if ($key === null) {
            return null;
        }

        $local = Storage::disk('local');
        if ($local->exists($key)) {
            return $local->path($key);
        }

        $cache = app(CatalogSyncState::class)->cache();
        $missingKey = $this->missingKey($key);
        if ($retryMissing) {
            $cache->forget($missingKey);
        } elseif ($cache->has($missingKey)) {
            return null;
        }

        return $cache->lock('logo-download:'.hash('sha256', $key), 120)->block(10, function () use ($page, $key, $local, $cache, $missingKey): ?string {
            if ($local->exists($key)) {
                return $local->path($key);
            }
            if (! Storage::disk('supabase')->exists($page->logo_path)) {
                $cache->put($missingKey, true, now()->addHours(6));

                return null;
            }

            $stream = Storage::disk('supabase')->readStream($page->logo_path);
            if (! is_resource($stream)) {
                throw new RuntimeException('Supabase no entregó la imagen solicitada.');
            }
            $temporary = $key.'.part-'.Str::uuid();
            try {
                if (! $local->put($temporary, $stream)) {
                    throw new RuntimeException('No se pudo guardar la imagen en la caché local.');
                }
            } finally {
                fclose($stream);
            }

            try {
                $absolute = $local->path($temporary);
                if (filesize($absolute) > self::MAX_BYTES || ! in_array(mime_content_type($absolute), self::IMAGE_TYPES, true)) {
                    throw new RuntimeException('Supabase entregó un archivo que no es una imagen admitida.');
                }
                if (! $local->move($temporary, $key)) {
                    throw new RuntimeException('No se pudo completar la caché de la imagen.');
                }

                return $local->path($key);
            } catch (Throwable $exception) {
                $local->delete($temporary);
                throw $exception;
            }
        });
    }

    /** @return array{cached: int, downloaded: int, missing: list<string>, errors: list<string>} */
    public function warm(bool $retryMissing = false): array
    {
        $summary = ['cached' => 0, 'downloaded' => 0, 'missing' => [], 'errors' => []];
        $pages = Pagina::query()->where('logo_disk', 'supabase')->whereNotNull('logo_path')->get();
        foreach ($pages as $page) {
            $cached = $this->isCached($page);
            try {
                if ($this->path($page, $retryMissing) === null) {
                    $summary['missing'][] = $page->slug;
                } else {
                    $summary[$cached ? 'cached' : 'downloaded']++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $summary['errors'][] = $page->slug;
            }
        }

        return $summary;
    }

    private function cacheKey(Pagina $page): ?string
    {
        $path = $page->logo_path;
        if ($page->logo_disk !== 'supabase' || ! is_string($path) || $path === ''
            || str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, '\\')
            || preg_match('~^https?://~i', $path)) {
            return null;
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return null;
        }

        return 'logo-cache/'.hash('sha256', $path).'.'.$extension;
    }

    private function missingKey(string $key): string
    {
        return 'logo-missing:'.hash('sha256', $key);
    }
}
