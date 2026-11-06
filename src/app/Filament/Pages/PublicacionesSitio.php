<?php

namespace App\Filament\Pages;

use App\Actions\RejectSaleAction;
use App\Filament\Resources\PaginaResource;
use App\Models\AlbumPagina;
use App\Models\Pagina;
use App\Support\BusinessLedger;
use App\Support\CatalogConnection;
use App\Support\CatalogSyncState;
use App\Support\PageSaleSnapshot;
use App\Support\PageTrash;
use App\Support\PublicationQueue;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicacionesSitio extends Page
{
    use WithPagination;

    private const BULK_REJECT_LIMIT = 25;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Páginas';

    protected static ?string $title = 'Páginas';

    protected static ?string $slug = 'publicaciones-sitio';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.publicaciones-sitio';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $filter = 'all';

    #[Url(except: 'recently_updated')]
    public string $sort = 'recently_updated';

    #[Url(except: 25)]
    public int $perPage = 25;

    public array $withdrawalReasons = [];

    public array $selectedPages = [];

    public string $bulkRejectReason = '';

    public string $albumName = '';

    public string $albumSector = '';

    public ?string $albumDate = null;

    #[Url(except: '')]
    public string $albumFilter = '';

    public string $targetAlbum = '';

    public string $editingAlbumId = '';

    public string $editingAlbumName = '';

    public string $editingAlbumSector = '';

    public ?string $editingAlbumDate = null;

    public function startEditingAlbum(): void
    {
        abort_unless(auth()->user()?->activo && $this->albumFilter !== '', 403);
        $album = AlbumPagina::findOrFail($this->albumFilter);
        $this->editingAlbumId = $album->id;
        $this->editingAlbumName = $album->nombre;
        $this->editingAlbumSector = $album->sector ?? '';
        $this->editingAlbumDate = $album->fecha_ruta?->format('Y-m-d');
    }

    public function saveAlbum(): void
    {
        abort_unless(auth()->user()?->activo && $this->editingAlbumId !== '', 403);
        $data = $this->validate([
            'editingAlbumName' => ['required', 'string', 'max:120'],
            'editingAlbumSector' => ['nullable', 'string', 'max:120'],
            'editingAlbumDate' => ['nullable', 'date'],
        ]);
        $album = AlbumPagina::findOrFail($this->editingAlbumId);
        CatalogConnection::transaction(function () use ($album, $data): AlbumPagina {
            $album->update([
                'nombre' => trim($data['editingAlbumName']),
                'sector' => trim((string) $data['editingAlbumSector']) ?: null,
                'fecha_ruta' => $data['editingAlbumDate'],
            ]);

            return $album;
        });
        $this->editingAlbumId = '';
        Notification::make()->success()->title('Álbum actualizado')->send();
    }

    public function createAlbum(): void
    {
        abort_unless(auth()->user()?->activo && Schema::connection(CatalogConnection::enabled() ? 'supabase' : config('database.default'))->hasTable('albumes_paginas'), 503);
        $data = $this->validate([
            'albumName' => ['required', 'string', 'max:120'],
            'albumSector' => ['nullable', 'string', 'max:120'],
            'albumDate' => ['nullable', 'date'],
        ]);
        $album = CatalogConnection::transaction(fn (): AlbumPagina => AlbumPagina::create([
            'nombre' => trim($data['albumName']),
            'sector' => trim((string) $data['albumSector']) ?: null,
            'fecha_ruta' => $data['albumDate'],
        ]));
        $this->albumName = $this->albumSector = '';
        $this->albumDate = null;
        $this->albumFilter = $album->id;
        $this->dispatch('album-created');
        Notification::make()->success()->title('Álbum creado')->send();
    }

    public function addSelectedToAlbum(): void
    {
        abort_unless(auth()->user()?->activo, 403);
        $album = AlbumPagina::findOrFail($this->targetAlbum);
        $ids = $this->selectedIds();
        CatalogConnection::transaction(function () use ($album, $ids): AlbumPagina {
            $pages = Pagina::query()->whereIn('id', $ids)->whereNull('papelera_en')->pluck('id')->all();
            abort_unless(count($pages) === count($ids), 409);
            $album->paginas()->syncWithoutDetaching($ids);

            return $album;
        });
        $this->selectedPages = [];
        Notification::make()->success()->title('Páginas agregadas al álbum')->send();
    }

    public function deleteAlbum(): void
    {
        abort_unless(auth()->user()?->activo, 403);
        $album = AlbumPagina::findOrFail($this->albumFilter);
        CatalogConnection::transaction(function () use ($album): AlbumPagina {
            $album->delete();

            return $album;
        });
        $this->albumFilter = '';
        Notification::make()->success()->title('Álbum eliminado; páginas conservadas')->send();
    }

    public function removePageFromAlbum(string $pageId): void
    {
        abort_unless(auth()->user()?->activo && $this->albumFilter !== '', 403);
        $album = AlbumPagina::findOrFail($this->albumFilter);
        CatalogConnection::transaction(function () use ($album, $pageId): AlbumPagina {
            $album->paginas()->detach($pageId);

            return $album;
        });
        Notification::make()->success()->title('Página quitada del álbum')->send();
    }

    #[On('catalog-synced')]
    #[On('publication-status-changed')]
    public function refreshFromStatus(): void
    {
        // Solo vuelve a pintar la lista cuando el botón "Conexión" detecta cambios.
    }

    public function mount(): void
    {
        $search = request()->query('search');
        if (is_string($search)) {
            $this->search = Str::limit(trim($search), 100, '');
        }
    }

    public function updatedSearch(): void
    {
        $this->selectedPages = [];
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->selectedPages = [];
        $this->resetPage();
    }

    public function updatedAlbumFilter(): void
    {
        $this->selectedPages = [];
        $this->editingAlbumId = '';
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->selectedPages = [];
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->selectedPages = [];
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filter = 'all';
        $this->albumFilter = '';
        $this->sort = 'recently_updated';
        $this->perPage = 25;
        $this->selectedPages = [];
        $this->editingAlbumId = '';
        $this->resetPage();
    }

    public function toggleFilter(string $filter): void
    {
        abort_unless(in_array($filter, ['for_sale', 'sold', 'rejected', 'trash'], true), 422);
        if ($filter === 'trash') {
            abort_unless(app(PageTrash::class)->available(), 503);
        }
        $this->filter = $this->filter === $filter ? 'all' : $filter;
        $this->selectedPages = [];
        $this->resetPage();
    }

    public function getViewData(): array
    {
        $sorts = [
            'recently_updated' => ['updated_at', 'desc'],
            'oldest_updated' => ['updated_at', 'asc'],
            'recently_created' => ['created_at', 'desc'],
            'oldest_created' => ['created_at', 'asc'],
            'name_asc' => ['nombre_publico', 'asc'],
            'name_desc' => ['nombre_publico', 'desc'],
        ];
        [$sortColumn, $sortDirection] = $sorts[$this->sort] ?? $sorts['recently_updated'];
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 25;
        $queue = app(PublicationQueue::class);
        $trash = app(PageTrash::class);
        $trashAvailable = $trash->available();
        $albumsAvailable = Schema::connection(CatalogConnection::enabled() ? 'local' : config('database.default'))->hasTable('albumes_paginas');
        $albums = $albumsAvailable ? AlbumPagina::query()->orderByDesc('fecha_ruta')->orderBy('nombre')->get() : collect();
        $scopedPages = function (Builder $query) use ($albumsAvailable): Builder {
            $query->when($albumsAvailable && $this->albumFilter !== '', fn (Builder $query): Builder => $query->whereHas('albumes', fn (Builder $albums): Builder => $albums->whereKey($this->albumFilter)))
                ->when($this->search !== '', function (Builder $query): void {
                    $term = '%'.mb_strtolower(trim($this->search)).'%';
                    $query->where(function (Builder $query) use ($term): void {
                        $query->whereRaw('LOWER(nombre_publico) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(slug) LIKE ?', [$term])
                            ->orWhereHas('local', fn (Builder $local): Builder => $local->whereRaw('LOWER(nombre_interno) LIKE ?', [$term]));
                    });
                });

            return $query;
        };
        $pages = $scopedPages(Pagina::query()->with('local'))
            ->when($trashAvailable, fn (Builder $query): Builder => $query->when($this->filter === 'trash', fn (Builder $query): Builder => $query->whereNotNull('papelera_en'), fn (Builder $query): Builder => $query->whereNull('papelera_en')))
            ->when(! $trashAvailable && $this->filter === 'trash', fn (Builder $query): Builder => $query->whereRaw('1 = 0'))
            ->when($this->filter === 'for_sale', fn (Builder $query): Builder => $query->where('estado_comercial', 'por_vender'))
            ->when($this->filter === 'sold', fn (Builder $query): Builder => $query->where('estado_comercial', 'vendida'))
            ->when($this->filter === 'rejected', fn (Builder $query): Builder => $query->where('estado_comercial', 'rechazada'))
            ->when($this->filter === 'active', fn (Builder $query): Builder => $query->where('activo', true))
            ->when($this->filter === 'pending', fn (Builder $query): Builder => $query->whereIn('publicacion_estado', ['publicando', 'retirada_pendiente', 'retirando']))
            ->when($this->filter === 'withdrawn', fn (Builder $query): Builder => $query->where('activo', false))
            ->when($this->filter === 'error', fn (Builder $query): Builder => $query->whereIn('publicacion_estado', ['fallida', 'retirada_fallida']))
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', $sortDirection)
            ->paginate($perPage);

        $ids = $pages->getCollection()->pluck('id')->all();
        $sales = $pages->getCollection()->filter(fn (Pagina $page): bool => $page->venta_id !== null)
            ->mapWithKeys(fn (Pagina $page): array => [$page->id => (object) [
                'id' => $page->venta_id,
                'estado' => match ($page->estado_comercial) {
                    'vendida' => 'confirmada',
                    'rechazada' => 'rechazada',
                    null => 'cancelacion_pendiente',
                    default => 'borrador',
                },
            ]]);
        $pageStatuses = $queue->pageStatuses($ids);
        $coverUrls = [];
        foreach ($pages as $page) {
            $coverUrls[$page->id] = $trash->coverUrl($page);
        }

        return [
            'pages' => $pages,
            'sales' => $sales,
            'pageStatuses' => $pageStatuses,
            'coverUrls' => $coverUrls,
            'trashAvailable' => $trashAvailable,
            'albums' => $albums,
            'albumsAvailable' => $albumsAvailable,
            'syncStatus' => CatalogConnection::enabled() ? app(CatalogSyncState::class)->status() : null,
            'selectableIds' => $pages->getCollection()->filter(fn (Pagina $page): bool => $page->papelera_en !== null
                ? $trash->isNeverPublished($page)
                : $trash->isUnpublishedDraft($page))->pluck('id')->all(),
            'draftsInTrash' => $trashAvailable ? $this->neverPublishedQuery()->whereNotNull('papelera_en')->count() : 0,
            'pageEditUrl' => fn (Pagina $page): string => PaginaResource::getUrl('edit', ['record' => $page]),
            'counts' => [
                'sold' => $scopedPages(Pagina::query())->when($trashAvailable, fn (Builder $query): Builder => $query->whereNull('papelera_en'))->where('estado_comercial', 'vendida')->count(),
                'for_sale' => $scopedPages(Pagina::query())->when($trashAvailable, fn (Builder $query): Builder => $query->whereNull('papelera_en'))->where('estado_comercial', 'por_vender')->count(),
                'rejected' => $scopedPages(Pagina::query())->when($trashAvailable, fn (Builder $query): Builder => $query->whereNull('papelera_en'))->where('estado_comercial', 'rechazada')->count(),
                'trash' => $trashAvailable ? $scopedPages(Pagina::query())->whereNotNull('papelera_en')->count() : 0,
            ],
        ];
    }

    public function publishPage(string $pageId): void
    {
        $page = Pagina::findOrFail($pageId);
        abort_unless($page->estado_comercial !== 'rechazada' && $page->publicacion_estado === 'fallida', 409);
        app(PublicationQueue::class)->request($page);
        Notification::make()->success()->title('Publicación solicitada')->body('Puedes seguir el estado desde aquí.')->send();
    }

    public function startSaleForPage(string $pageId): void
    {
        abort_unless(auth()->user()?->activo, 403);
        $database = app(BusinessLedger::class)->connection();
        $saleId = $database->transaction(function () use ($database, $pageId): string {
            $page = $database->table('paginas')->where('id', $pageId)->lockForUpdate()->first();
            abort_unless($page && $page->estado_comercial === 'por_vender' && $page->papelera_en === null, 409);
            $existing = $database->table('ventas')->where('pagina_id', $pageId)->first();
            if ($existing) {
                abort_unless($existing->estado === 'borrador', 409);
                if ($page->venta_id !== $existing->id) {
                    $database->table('paginas')->where('id', $pageId)->update(['venta_id' => $existing->id]);
                }

                return $existing->id;
            }
            $id = (string) Str::uuid();
            $database->table('ventas')->insert([
                'id' => $id, 'estado' => 'borrador', 'pagina_id' => $pageId,
                'registrado_por' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $database->table('paginas')->where('id', $pageId)->update(['venta_id' => $id]);

            return $id;
        }, 3);
        app(PageSaleSnapshot::class)->invalidate($database);
        $page = CatalogConnection::remoteReads(fn (): Pagina => Pagina::findOrFail($pageId));
        CatalogConnection::refreshAfterWrite($page);
        $this->redirect(Ventas::getUrl(['venta' => $saleId]), navigate: true);
    }

    public function unpublishPage(string $pageId): void
    {
        abort(410, 'Usa Rechazar para retirar una página Por vender.');
    }

    public function withdrawPage(string $pageId): void
    {
        abort_unless(auth()->user()?->activo, 403);
        abort_if(Pagina::query()->whereKey($pageId)->whereNotNull('papelera_en')->exists(), 409);
        abort_unless(Pagina::query()->whereKey($pageId)->where('estado_comercial', 'por_vender')->exists(), 409);
        $reason = trim((string) ($this->withdrawalReasons[$pageId] ?? ''));
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(["withdrawalReasons.{$pageId}" => 'Indica un motivo de 3 a 500 caracteres.']);
        }

        $this->rejectPage($pageId, $reason);

        unset($this->withdrawalReasons[$pageId]);
        Notification::make()->warning()->title('Página desactivada; retirada pública pendiente')->body('El acceso puede seguir visible hasta comprobar el despliegue.')->send();
    }

    public function rejectSelectedPages(): void
    {
        abort_unless(auth()->user()?->activo, 403);
        $ids = $this->selectedIds();
        $reason = trim($this->bulkRejectReason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['bulkRejectReason' => 'Indica un motivo de 3 a 500 caracteres.']);
        }
        if (count($ids) > self::BULK_REJECT_LIMIT) {
            throw ValidationException::withMessages(['bulkRejectReason' => 'Puedes rechazar hasta '.self::BULK_REJECT_LIMIT.' páginas por vez. Reduce la selección.']);
        }

        $names = Pagina::query()->whereIn('id', $ids)->pluck('nombre_publico', 'id');
        $rejected = 0;
        $skipped = [];
        foreach ($ids as $id) {
            $label = $names[$id] ?? $id;
            try {
                abort_if(Pagina::query()->whereKey($id)->whereNotNull('papelera_en')->exists(), 409, 'Está en la papelera.');
                abort_unless(Pagina::query()->whereKey($id)->where('estado_comercial', 'por_vender')->exists(), 409, 'Solo se rechazan páginas Por vender.');
                $this->rejectPage($id, $reason);
                $rejected++;
            } catch (HttpException $exception) {
                $skipped[] = $label.' ('.($exception->getMessage() ?: 'no se pudo rechazar').')';
            }
        }

        $this->selectedPages = [];
        $this->bulkRejectReason = '';
        $notification = Notification::make()
            ->title($rejected.' '.($rejected === 1 ? 'página rechazada' : 'páginas rechazadas').'; retirada pública pendiente')
            ->body($skipped === []
                ? 'El acceso puede seguir visible hasta comprobar el despliegue.'
                : 'Omitidas '.count($skipped).': '.implode('; ', array_slice($skipped, 0, 5)).(count($skipped) > 5 ? '…' : ''));
        ($skipped === [] ? $notification->warning() : ($rejected === 0 ? $notification->danger() : $notification->warning()))->send();
    }

    private function rejectPage(string $pageId, string $reason): void
    {
        $sale = app(BusinessLedger::class)->connection()->table('ventas')->where('pagina_id', $pageId)->first();
        if ($sale && in_array($sale->estado, ['borrador', 'confirmada'], true)) {
            app(RejectSaleAction::class)->handle(auth()->user(), $sale->id, $reason);
        } elseif ($sale) {
            abort(409, 'La venta ya no está en un estado que permita rechazar esta página.');
        } else {
            $page = CatalogConnection::transaction(function () use ($pageId, $reason): Pagina {
                $page = Pagina::query()->whereKey($pageId)->lockForUpdate()->firstOrFail();
                abort_unless($page->estado_comercial === 'por_vender' && $page->papelera_en === null, 409);
                $page->forceFill([
                    'activo' => false,
                    'estado_comercial' => 'rechazada',
                    'publicacion_estado' => 'retirada_pendiente',
                    'actualizada_por' => auth()->id(),
                ])->save();
                CatalogConnection::connection()->table('eventos_auditoria')->insert([
                    'id' => (string) Str::uuid(),
                    'administrador_id' => auth()->id(),
                    'entidad_tipo' => 'pagina',
                    'entidad_id' => $pageId,
                    'accion' => 'pagina_rechazada',
                    'ocurrido_en' => now(),
                    'resumen' => 'Página rechazada sin venta asociada',
                    'motivo' => $reason,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $page;
            });
            app(PublicationQueue::class)->requestWithdrawal($page);
        }
    }

    public function retryWithdrawal(string $pageId): void
    {
        $page = Pagina::findOrFail($pageId);
        abort_unless($page->estado_comercial === 'rechazada' && $page->publicacion_estado === 'retirada_fallida', 409);
        app(PublicationQueue::class)->requestWithdrawal($page);
        Notification::make()->success()->title('Retirada solicitada')->send();
    }

    public function reactivatePage(string $pageId): void
    {
        abort_unless(auth()->user()?->activo, 403);
        $candidate = Pagina::findOrFail($pageId);
        abort_unless($candidate->estado_comercial === 'por_vender', 409);
        abort_if($candidate->papelera_en !== null, 409, 'Restaura la página desde la papelera primero.');
        $allowed = config('clicktap.publication_slugs');
        abort_if(is_array($allowed) && ! in_array($candidate->slug, $allowed, true)
            && ! app(PageTrash::class)->isUnpublishedDraft($candidate), 422, 'Esta página no está incluida en la selección de publicación.');
        $linkedSale = app(BusinessLedger::class)->connection()->table('ventas')->where('pagina_id', $pageId)->first();
        abort_if($linkedSale && in_array($linkedSale->estado, ['rechazada', 'cancelacion_pendiente'], true), 409, 'Resuelve primero la situación comercial de la venta.');

        $page = CatalogConnection::transaction(function () use ($pageId): Pagina {
            $page = Pagina::query()->whereKey($pageId)->lockForUpdate()->firstOrFail();
            abort_unless($page->estado_comercial === 'por_vender', 409);
            abort_if($page->papelera_en !== null, 409, 'Restaura la página desde la papelera primero.');
            $allowed = config('clicktap.publication_slugs');
            abort_if(is_array($allowed) && ! in_array($page->slug, $allowed, true)
                && ! app(PageTrash::class)->isUnpublishedDraft($page), 422, 'Esta página no está incluida en la selección de publicación.');
            $page->forceFill([
                'activo' => true,
                'publicacion_estado' => 'sin_publicar',
                'actualizada_por' => auth()->id(),
            ])->save();

            return $page;
        });
        app(PublicationQueue::class)->request($page);
        Notification::make()->success()->title('Página reactivada; publicación solicitada')->send();
    }

    public function moveToTrash(string $pageId): void
    {
        app(PageTrash::class)->move($pageId);
        Notification::make()->warning()->title('Página movida a la papelera')->body('Se conserva 30 días. Si estuvo publicada, espera a que se confirme la retirada del sitio.')->send();
    }

    public function moveSelectedToTrash(): void
    {
        $this->guardBulkAction();
        abort_if($this->filter === 'trash', 409);
        $ids = $this->selectedIds();

        $trash = app(PageTrash::class);
        foreach ($ids as $id) {
            $trash->move($id);
        }
        $this->selectedPages = [];
        Notification::make()->warning()->title(count($ids).' páginas movidas a la papelera')
            ->body('Las que estuvieron publicadas esperan la confirmación de retirada de Cloudflare.')->send();
    }

    public function publishSelectedDrafts(): void
    {
        abort_unless(auth()->user()?->activo, 403);
        abort_if($this->filter === 'trash', 409);
        $ids = $this->selectedIds();
        $pages = CatalogConnection::remoteReads(fn () => Pagina::query()->whereIn('id', $ids)->get());
        abort_unless($pages->count() === count($ids), 409, 'La selección cambió. Vuelve a elegir los borradores.');
        $trash = app(PageTrash::class);
        foreach ($pages as $page) {
            abort_unless($trash->isUnpublishedDraft($page), 409, 'Solo se pueden publicar borradores que nunca se han publicado.');
        }
        abort_if(app(BusinessLedger::class)->connection()->table('ventas')
            ->whereIn('pagina_id', $ids)
            ->whereIn('estado', ['rechazada', 'cancelacion_pendiente'])
            ->exists(), 409, 'Resuelve primero las ventas rechazadas de la selección.');

        $queue = app(PublicationQueue::class);
        foreach ($ids as $id) {
            $page = CatalogConnection::transaction(function () use ($id, $trash): Pagina {
                $page = Pagina::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                abort_unless($trash->isUnpublishedDraft($page), 409, 'La página ya no es un borrador sin publicar.');
                abort_if(app(BusinessLedger::class)->connection()->table('ventas')
                    ->where('pagina_id', $id)
                    ->whereIn('estado', ['rechazada', 'cancelacion_pendiente'])
                    ->exists(), 409, 'Resuelve primero la venta rechazada.');
                if (! $page->activo) {
                    $page->forceFill(['activo' => true, 'actualizada_por' => auth()->id()])->save();
                }

                return $page;
            });
            $queue->request($page);
        }
        $this->selectedPages = [];
        Notification::make()->success()->title(count($ids).' publicaciones solicitadas')
            ->body('Los borradores se activaron y el worker los enviará a Cloudflare. Sigue el progreso desde aquí.')->send();
    }

    public function deleteSelectedDrafts(): void
    {
        $this->guardBulkAction();
        abort_unless($this->filter === 'trash', 409);
        $ids = $this->selectedIds();
        $eligible = $this->neverPublishedQuery()->whereNotNull('papelera_en')->whereIn('id', $ids)->count();
        abort_unless($eligible === count($ids), 409, 'La selección incluye páginas que estuvieron publicadas.');

        $trash = app(PageTrash::class);
        foreach ($ids as $id) {
            $trash->purgeDraft($id);
        }
        $this->selectedPages = [];
        Notification::make()->success()->title(count($ids).' borradores eliminados definitivamente')->send();
    }

    public function deleteSelectedDraftsDirectly(): void
    {
        $this->guardBulkAction();
        abort_if($this->filter === 'trash', 409);
        $ids = $this->selectedIds();

        $trash = app(PageTrash::class);
        $deleted = 0;
        foreach ($ids as $id) {
            $page = Pagina::query()->findOrFail($id);
            if ($trash->isUnpublishedDraft($page)) {
                $trash->moveDraft($id);
                $trash->purgeDraft($id);
                $deleted++;
            } else {
                $trash->move($id);
            }
        }
        $this->selectedPages = [];
        Notification::make()->success()->title($deleted.' borradores eliminados definitivamente')
            ->body((count($ids) - $deleted).' publicadas pasaron a la papelera; se eliminarán cuando se confirme su retirada de Cloudflare.')->send();
    }

    public function emptyDraftTrash(): void
    {
        $this->guardBulkAction();
        abort_unless($this->filter === 'trash', 409);
        $ids = $this->neverPublishedQuery()->whereNotNull('papelera_en')->pluck('id');
        $trash = app(PageTrash::class);
        foreach ($ids as $id) {
            $trash->purgeDraft($id);
        }
        $this->selectedPages = [];
        Notification::make()->success()->title($ids->count().' borradores eliminados de la papelera')->send();
    }

    private function guardBulkAction(): void
    {
        abort_unless(auth()->user()?->activo, 403);
        abort_unless(app(PageTrash::class)->available(), 503, 'La papelera aún no está disponible.');
    }

    /** @return list<string> */
    private function selectedIds(): array
    {
        abort_unless(count($this->selectedPages) > 0 && count($this->selectedPages) <= 100, 422);
        foreach ($this->selectedPages as $id) {
            abort_unless(is_string($id) && $id !== '', 422);
        }

        return array_values(array_unique($this->selectedPages));
    }

    private function neverPublishedQuery(): Builder
    {
        return Pagina::query()
            ->where('publicacion_estado', 'sin_publicar')
            ->whereNull('publicacion_commit')
            ->whereNull('publicada_en');
    }

    public function restoreFromTrash(string $pageId): void
    {
        app(PageTrash::class)->restore($pageId);
        Notification::make()->success()->title('Página restaurada como inactiva')->body('Puedes reactivarla desde Publicaciones cuando corresponda.')->send();
    }

    public function deleteFromTrash(string $pageId): void
    {
        abort_unless(auth()->user()?->activo, 403);
        app(PageTrash::class)->purge($pageId);
        Notification::make()->success()->title('Página eliminada definitivamente')->send();
    }

    public function downloadBackup(string $pageId): StreamedResponse
    {
        abort_unless(auth()->user()?->activo, 403);
        abort_unless(app(PageTrash::class)->available(), 503, 'La papelera aún no está disponible.');
        $page = Pagina::query()->whereNotNull('papelera_en')->findOrFail($pageId);
        $backup = app(PageTrash::class)->backup($page);

        return response()->streamDownload(function () use ($backup): void {
            echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }, 'clicktap-'.$page->slug.'-respaldo.json', ['Content-Type' => 'application/json']);
    }
}
