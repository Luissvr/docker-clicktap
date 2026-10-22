<?php

namespace App\Filament\Resources\PaginaResource\Pages;

use App\Filament\Resources\PaginaResource;
use App\Filament\Resources\PaginaResource\Pages\Concerns\LoadsPagePreview;
use App\Filament\Resources\PaginaResource\Pages\Concerns\OrdersPageLinks;
use App\Models\LocalNegocio;
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

        return parent::handleRecordCreation($data);
    }
}
