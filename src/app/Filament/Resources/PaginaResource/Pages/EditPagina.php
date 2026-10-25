<?php

namespace App\Filament\Resources\PaginaResource\Pages;

use App\Filament\Pages\Ventas;
use App\Filament\Resources\PaginaResource;
use App\Filament\Resources\PaginaResource\Pages\Concerns\LoadsPagePreview;
use App\Filament\Resources\PaginaResource\Pages\Concerns\OrdersPageLinks;
use App\Support\BusinessLedger;
use App\Support\ProtectsCatalogEdits;
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['actualizada_por'] = auth()->id();

        return $data;
    }
}
