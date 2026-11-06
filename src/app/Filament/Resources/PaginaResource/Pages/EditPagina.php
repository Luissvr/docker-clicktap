<?php

namespace App\Filament\Resources\PaginaResource\Pages;

use App\Filament\Pages\PublicacionesSitio;
use App\Filament\Pages\Ventas;
use App\Filament\Resources\PaginaResource;
use App\Filament\Resources\PaginaResource\Pages\Concerns\LoadsPagePreview;
use App\Filament\Resources\PaginaResource\Pages\Concerns\OrdersPageLinks;
use App\Support\BusinessLedger;
use App\Support\LogoCache;
use App\Support\PageTrash;
use App\Support\ProtectsCatalogEdits;
use App\Support\PublicationQueue;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class EditPagina extends EditRecord
{
    use LoadsPagePreview;
    use OrdersPageLinks;
    use ProtectsCatalogEdits {
        commitDatabaseTransaction as commitCatalogTransaction;
    }

    protected static string $resource = PaginaResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    #[Locked]
    public ?string $ventaId = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $saleId = request()->query('venta_id');
        if (is_string($saleId)) {
            abort_unless(app(BusinessLedger::class)->connection()->table('ventas')
                ->where('id', $saleId)->where('pagina_id', $this->getRecord()->getKey())->exists(), 404);
            $this->ventaId = $saleId;
        }
    }

    public function hydrate(): void
    {
        parent::hydrate();

        if (is_string($this->data['logo_path'] ?? null)) {
            $this->data['logo_path'] = [(string) Str::uuid() => $this->data['logo_path']];
        }
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->ventaId ? Ventas::getUrl(['venta' => $this->ventaId]) : PublicacionesSitio::getUrl();
    }

    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            Action::make('deleteDraft')
                ->label('Eliminar borrador')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Enviar borrador a la papelera')
                ->modalDescription('La página dejará de aparecer en Páginas. Puedes restaurarla desde Publicaciones durante 30 días.')
                ->modalSubmitActionLabel('Mover a la papelera')
                ->visible(fn (): bool => app(PageTrash::class)->available() && app(PageTrash::class)->isUnpublishedDraft($this->getRecord()))
                ->action(fn () => $this->deleteDraft()),
        ];
    }

    public function deleteDraft(): void
    {
        app(PageTrash::class)->moveDraft($this->getRecord()->getKey());
        Notification::make()->success()->title('Borrador enviado a la papelera')->body('Puedes restaurarlo durante 30 días desde Publicaciones.')->send();
        $this->redirect(PaginaResource::getUrl());
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['logo_path'] ?? null) === null && app(LogoCache::class)->isKnownMissing($this->getRecord())) {
            unset($data['logo_path']);
        }
        unset($data['contenido_extra']['place_id']);
        $data['actualizada_por'] = auth()->id();

        return $data;
    }

    protected function commitDatabaseTransaction(): void
    {
        $this->commitCatalogTransaction();
        $page = $this->getRecord();
        if ($page && $page->activo && $page->papelera_en === null) {
            try {
                app(PublicationQueue::class)->request($page);
            } catch (\Throwable $exception) {
                report($exception);
                Notification::make()->warning()->persistent()->title('Cambios guardados; publicación pendiente')
                    ->body('No se pudo solicitar el envío. Revisa el estado técnico para reintentarlo.')->send();
            }
        }
    }
}
