<?php

namespace App\Filament\Resources\LocalNegocioResource\Pages;

use App\Filament\Resources\LocalNegocioResource;
use App\Support\ProtectsCatalogEdits;
use Filament\Resources\Pages\EditRecord;

class EditLocal extends EditRecord
{
    use ProtectsCatalogEdits;

    protected static string $resource = LocalNegocioResource::class;
}
