<?php

namespace App\Filament\Resources\LocalNegocioResource\Pages;

use App\Filament\Resources\LocalNegocioResource;
use App\Support\UsesCatalogTransactions;
use Filament\Resources\Pages\CreateRecord;

class CreateLocal extends CreateRecord
{
    use UsesCatalogTransactions;

    public function create(bool $another = false): void
    {
        $this->performCatalogOperation(fn () => parent::create($another));
    }

    protected static string $resource = LocalNegocioResource::class;
}
