<?php

namespace App\Filament\Resources\PaginaResource\Pages;

use App\Filament\Resources\PaginaResource;
use App\Filament\Resources\PaginaResource\Pages\Concerns\LoadsPagePreview;
use App\Filament\Resources\PaginaResource\Pages\Concerns\OrdersPageLinks;
use App\Support\ProtectsCatalogEdits;
use Filament\Resources\Pages\EditRecord;

class EditPagina extends EditRecord
{
    use LoadsPagePreview;
    use OrdersPageLinks;
    use ProtectsCatalogEdits;

    protected static string $resource = PaginaResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['actualizada_por'] = auth()->id();

        return $data;
    }
}
