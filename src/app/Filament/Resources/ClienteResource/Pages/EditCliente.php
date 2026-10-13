<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use App\Support\ProtectsCatalogEdits;
use Filament\Resources\Pages\EditRecord;

class EditCliente extends EditRecord
{
    use ProtectsCatalogEdits;

    protected static string $resource = ClienteResource::class;
}
