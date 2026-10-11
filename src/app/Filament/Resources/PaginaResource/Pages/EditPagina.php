<?php

namespace App\Filament\Resources\PaginaResource\Pages;

use App\Filament\Resources\PaginaResource;
use Filament\Resources\Pages\EditRecord;

class EditPagina extends EditRecord
{
    protected static string $resource = PaginaResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['actualizada_por'] = auth()->id();

        return $data;
    }
}
