<?php

namespace App\Filament\Pages;

use App\Actions\RejectSaleAction;
use App\Filament\Resources\PaginaResource;
use App\Models\Pagina;
use App\Support\BusinessLedger;
use App\Support\CatalogConnection;
use App\Support\PageTrash;
use App\Support\PublicationQueue;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicacionesSitio extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-up';

    protected static ?string $navigationLabel = 'Publicaciones';

    protected static ?string $title = 'Publicaciones';

    protected static ?string $slug = 'publicaciones-sitio';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.publicaciones-sitio';

    public string $search = '';

    public string $filter = 'all';

    public array $withdrawalReasons = [];

    public array $selectedPages = [];

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

    public function toggleFilter(string $filter): void
    {
        abort_unless(in_array($filter, ['published', 'for_sale', 'rejected', 'trash'], true), 422);
        if ($filter === 'trash') {
            abort_unless(app(PageTrash::class)->available(), 503);
        }
        $this->filter = $this->filter === $filter ? 'all' : $filter;
        $this->selectedPages = [];
        $this->resetPage();
    }

    public function getViewData(): array
    {
        $queue = app(PublicationQueue::class);
        $trash = app(PageTrash::class);
        $trashAvailable = $trash->available();
        $salesQuery = app(BusinessLedger::class)->connection()->table('ventas');
        $rejectedIds = (clone $salesQuery)->whereIn('estado', ['rechazada', 'cancelacion_pendiente'])->whereNotNull('pagina_id')->pluck('pagina_id')->all();
        $confirmedIds = (clone $salesQuery)->where('estado', 'confirmada')->whereNotNull('pagina_id')->pluck('pagina_id')->all();
        $pages = Pagina::query()
            ->with('local')
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.mb_strtolower(trim($this->search)).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->whereRaw('LOWER(nombre_publico) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$term])
                        ->orWhereHas('local', fn (Builder $local): Builder => $local->whereRaw('LOWER(nombre_interno) LIKE ?', [$term]));
                });
            })
            ->when($trashAvailable, fn (Builder $query): Builder => $query->when($this->filter === 'trash', fn (Builder $query): Builder => $query->whereNotNull('papelera_en'), fn (Builder $query): Builder => $query->whereNull('papelera_en')))
            ->when(! $trashAvailable && $this->filter === 'trash', fn (Builder $query): Builder => $query->whereRaw('1 = 0'))
            ->when($this->filter === 'published', fn (Builder $query): Builder => $query->where('activo', true)->where('publicacion_estado', 'publicada'))
            ->when($this->filter === 'for_sale', fn (Builder $query): Builder => $query->where('activo', true)->where('publicacion_estado', 'sin_publicar')->whereNotIn('id', array_merge($rejectedIds, $confirmedIds)))
            ->when($this->filter === 'rejected', fn (Builder $query): Builder => $query->whereIn('id', $rejectedIds))
            ->when($this->filter === 'active', fn (Builder $query): Builder => $query->where('activo', true))
            ->when($this->filter === 'pending', fn (Builder $query): Builder => $query->whereIn('publicacion_estado', ['publicando', 'retirada_pendiente', 'retirando']))
            ->when($this->filter === 'withdrawn', fn (Builder $query): Builder => $query->where('activo', false))
            ->when($this->filter === 'error', fn (Builder $query): Builder => $query->whereIn('publicacion_estado', ['fallida', 'retirada_fallida']))
            ->orderByDesc('updated_at')
            ->paginate(12);

        $ids = $pages->getCollection()->pluck('id')->all();
        $sales = $ids === [] ? collect() : (clone $salesQuery)
            ->whereIn('pagina_id', $ids)->get()->keyBy('pagina_id');
        $pageStatuses = [];
        foreach ($pages as $page) {
            $pageStatuses[$page->id] = $queue->pageStatus($page->id);
        }
        $coverUrls = [];
        foreach ($pages as $page) {
            $coverUrls[$page->id] = $trash->coverUrl($page);
        }

        return $queue->status() + [
            'pages' => $pages,
            'sales' => $sales,
            'pageStatuses' => $pageStatuses,
            'coverUrls' => $coverUrls,
            'trashAvailable' => $trashAvailable,
            'selectableIds' => $pages->getCollection()->filter(fn (Pagina $page): bool => $page->papelera_en !== null
                ? $trash->isNeverPublished($page)
                : $trash->isUnpublishedDraft($page))->pluck('id')->all(),
            'draftsInTrash' => $trashAvailable ? $this->neverPublishedQuery()->whereNotNull('papelera_en')->count() : 0,
            'pageEditUrl' => fn (Pagina $page): string => PaginaResource::getUrl('edit', ['record' => $page]),
            'counts' => [
                'published' => Pagina::query()->when($trashAvailable, fn (Builder $query): Builder => $query->whereNull('papelera_en'))->where('activo', true)->where('publicacion_estado', 'publicada')->count(),
                'for_sale' => Pagina::query()->when($trashAvailable, fn (Builder $query): Builder => $query->whereNull('papelera_en'))->where('activo', true)->where('publicacion_estado', 'sin_publicar')->whereNotIn('id', array_merge($rejectedIds, $confirmedIds))->count(),
                'rejected' => Pagina::query()->when($trashAvailable, fn (Builder $query): Builder => $query->whereNull('papelera_en'))->whereIn('id', $rejectedIds)->count(),
                'trash' => $trashAvailable ? Pagina::query()->whereNotNull('papelera_en')->count() : 0,
            ],
        ];
    }

    public function publishPage(string $pageId): void
    {
        $page = Pagina::findOrFail($pageId);
        app(PublicationQueue::class)->request($page);
        Notification::make()->success()->title('Publicación solicitada')->body('Puedes seguir el estado desde aquí.')->send();
    }

    public function unpublishPage(string $pageId): void
    {
        abort_unless(auth()->user()?->activo, 403);
        $page = CatalogConnection::transaction(function () use ($pageId): Pagina {
            $page = Pagina::query()->whereKey($pageId)->lockForUpdate()->firstOrFail();
            abort_unless($page->activo && $page->papelera_en === null, 409);
            $page->forceFill([
                'activo' => false,
                'publicacion_estado' => 'retirada_pendiente',
                'actualizada_por' => auth()->id(),
            ])->save();

            return $page;
        });
        app(PublicationQueue::class)->requestWithdrawal($page);
        Notification::make()->warning()->title('Retirada solicitada')->body('La página puede seguir visible hasta comprobar el despliegue.')->send();
    }

    public function withdrawPage(string $pageId): void
    {
        abort_unless(auth()->user()?->activo, 403);
        abort_if(Pagina::query()->whereKey($pageId)->whereNotNull('papelera_en')->exists(), 409);
        $reason = trim((string) ($this->withdrawalReasons[$pageId] ?? ''));
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(["withdrawalReasons.{$pageId}" => 'Indica un motivo de 3 a 500 caracteres.']);
        }

        $sale = app(BusinessLedger::class)->connection()->table('ventas')->where('pagina_id', $pageId)->first();
        if ($sale && in_array($sale->estado, ['borrador', 'confirmada'], true)) {
            app(RejectSaleAction::class)->handle(auth()->user(), $sale->id, $reason);
        } elseif ($sale && in_array($sale->estado, ['rechazada', 'cancelacion_pendiente'], true)) {
            $this->retryWithdrawal($pageId);
        } else {
            $page = CatalogConnection::transaction(function () use ($pageId): Pagina {
                $page = Pagina::query()->whereKey($pageId)->lockForUpdate()->firstOrFail();
                $page->forceFill([
                    'activo' => false,
                    'publicacion_estado' => 'retirada_pendiente',
                    'actualizada_por' => auth()->id(),
                ])->save();

                return $page;
            });
            app(PublicationQueue::class)->requestWithdrawal($page);
        }

        unset($this->withdrawalReasons[$pageId]);
        Notification::make()->warning()->title('Página desactivada; retirada pública pendiente')->body('El acceso puede seguir visible hasta comprobar el despliegue.')->send();
    }

    public function retryWithdrawal(string $pageId): void
    {
        $page = Pagina::findOrFail($pageId);
        app(PublicationQueue::class)->requestWithdrawal($page);
        Notification::make()->success()->title('Retirada solicitada')->send();
    }

    public function reactivatePage(string $pageId): void
    {
        abort_unless(auth()->user()?->activo, 403);
        $candidate = Pagina::findOrFail($pageId);
        abort_if($candidate->papelera_en !== null, 409, 'Restaura la página desde la papelera primero.');
        $allowed = config('clicktap.publication_slugs');
        abort_if(is_array($allowed) && ! in_array($candidate->slug, $allowed, true), 422, 'Esta página no está incluida en la selección de publicación.');
        $linkedSale = app(BusinessLedger::class)->connection()->table('ventas')->where('pagina_id', $pageId)->first();
        abort_if($linkedSale && in_array($linkedSale->estado, ['rechazada', 'cancelacion_pendiente'], true), 409, 'Resuelve primero la situación comercial de la venta.');

        $page = CatalogConnection::transaction(function () use ($pageId): Pagina {
            $page = Pagina::query()->whereKey($pageId)->lockForUpdate()->firstOrFail();
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

    public function moveSelectedDraftsToTrash(): void
    {
        $this->guardBulkAction();
        abort_if($this->filter === 'trash', 409);
        $ids = $this->selectedIds();
        $eligible = $this->neverPublishedQuery()->whereNull('papelera_en')->whereIn('id', $ids)->count();
        abort_unless($eligible === count($ids), 409, 'La selección incluye páginas que no son borradores sin publicar.');

        $trash = app(PageTrash::class);
        foreach ($ids as $id) {
            $trash->moveDraft($id);
        }
        $this->selectedPages = [];
        Notification::make()->warning()->title(count($ids).' borradores movidos a la papelera')->send();
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
        $eligible = $this->neverPublishedQuery()->whereNull('papelera_en')->whereIn('id', $ids)->count();
        abort_unless($eligible === count($ids), 409, 'La selección incluye páginas que no son borradores sin publicar.');

        $trash = app(PageTrash::class);
        foreach ($ids as $id) {
            $trash->moveDraft($id);
            $trash->purgeDraft($id);
        }
        $this->selectedPages = [];
        Notification::make()->success()->title(count($ids).' borradores eliminados definitivamente')->send();
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
