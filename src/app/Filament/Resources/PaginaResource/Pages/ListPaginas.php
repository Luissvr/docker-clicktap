<?php

namespace App\Filament\Resources\PaginaResource\Pages;

use App\Filament\Resources\PaginaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListPaginas extends ListRecords
{
    protected static string $resource = PaginaResource::class;

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
