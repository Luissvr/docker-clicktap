<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListClientes extends ListRecords
{
    protected static string $resource = ClienteResource::class;

    #[On('catalog-synced')]
    public function refreshCatalogTable(): void
    {
        $this->resetPage();
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
