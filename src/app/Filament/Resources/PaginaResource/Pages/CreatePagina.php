<?php

namespace App\Filament\Resources\PaginaResource\Pages;

use App\Filament\Pages\Ventas;
use App\Filament\Resources\PaginaResource;
use App\Filament\Resources\PaginaResource\Pages\Concerns\LoadsPagePreview;
use App\Filament\Resources\PaginaResource\Pages\Concerns\OrdersPageLinks;
use App\Models\LocalNegocio;
use App\Support\BusinessLedger;
use App\Support\UsesCatalogTransactions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePagina extends CreateRecord
{
    use LoadsPagePreview;
    use OrdersPageLinks;
    use UsesCatalogTransactions;

    public function create(bool $another = false): void
    {
        $this->performCatalogOperation(fn () => parent::create($another));
    }

    protected static string $resource = PaginaResource::class;

    public ?string $ventaId = null;

    public function mount(): void
    {
        parent::mount();
        $saleId = request()->query('venta_id');
        if (! is_string($saleId)) {
            return;
        }

        $sale = app(BusinessLedger::class)->connection()->table('ventas')->where('id', $saleId)->where('estado', 'borrador')->first();
        abort_unless($sale, 404);
        abort_if($sale->pagina_id, 409, 'Este borrador ya tiene una página.');
        $this->ventaId = $saleId;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['actualizada_por'] = auth()->id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $local = LocalNegocio::query()->create([
            'nombre_interno' => $data['nombre_publico'],
        ]);
        $data['local_id'] = $local->getKey();

        $page = parent::handleRecordCreation($data);

        if ($this->ventaId) {
            app(BusinessLedger::class)->connection()->table('ventas')->where('id', $this->ventaId)
                ->where('estado', 'borrador')->update([
                    'pagina_id' => $page->getKey(),
                    'updated_at' => now(),
                ]);
        }

        return $page;
    }

    protected function getRedirectUrl(): string
    {
        return $this->ventaId
            ? Ventas::getUrl(['venta' => $this->ventaId])
            : parent::getRedirectUrl();
    }
}
