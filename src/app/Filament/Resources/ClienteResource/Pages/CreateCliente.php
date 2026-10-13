<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use App\Support\UsesCatalogTransactions;
use Filament\Resources\Pages\CreateRecord;

class CreateCliente extends CreateRecord
{
    use UsesCatalogTransactions;

    public function create(bool $another = false): void
    {
        $this->performCatalogOperation(fn () => parent::create($another));
    }

    protected static string $resource = ClienteResource::class;
}
