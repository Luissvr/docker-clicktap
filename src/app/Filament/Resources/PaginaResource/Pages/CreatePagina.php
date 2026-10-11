<?php

namespace App\Filament\Resources\PaginaResource\Pages;

use App\Filament\Resources\PaginaResource;
use App\Models\LocalNegocio;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePagina extends CreateRecord
{
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
