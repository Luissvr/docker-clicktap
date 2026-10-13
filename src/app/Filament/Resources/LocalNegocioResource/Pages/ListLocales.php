<?php

namespace App\Filament\Resources\LocalNegocioResource\Pages;

use App\Filament\Resources\LocalNegocioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListLocales extends ListRecords
{
    protected static string $resource = LocalNegocioResource::class;

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
