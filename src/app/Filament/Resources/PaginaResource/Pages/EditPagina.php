<?php

namespace App\Filament\Resources\PaginaResource\Pages;

use App\Filament\Pages\Ventas;
use App\Filament\Resources\PaginaResource;
use App\Filament\Resources\PaginaResource\Pages\Concerns\LoadsPagePreview;
use App\Filament\Resources\PaginaResource\Pages\Concerns\OrdersPageLinks;
use App\Support\BusinessLedger;
use App\Support\PageTrash;
use App\Support\ProtectsCatalogEdits;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\Locked;

class EditPagina extends EditRecord
{
    use LoadsPagePreview;
    use OrdersPageLinks;
    use ProtectsCatalogEdits;

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

    protected function getRedirectUrl(): ?string
    {
        return $this->ventaId ? Ventas::getUrl(['venta' => $this->ventaId]) : parent::getRedirectUrl();
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
        unset($data['contenido_extra']['place_id']);
        $data['actualizada_por'] = auth()->id();

        return $data;
    }
}
