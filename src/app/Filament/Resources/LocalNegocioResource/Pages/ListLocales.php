<?php

namespace App\Filament\Resources\LocalNegocioResource\Pages;

use App\Filament\Resources\LocalNegocioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLocales extends ListRecords
{
    protected static string $resource = LocalNegocioResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
