<?php

namespace App\Filament\Resources\PaginaResource\Pages;

use App\Filament\Resources\PaginaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListPaginas extends ListRecords
{
    protected static string $resource = PaginaResource::class;

    public string $paginaSort = 'updated_at:desc';

    public function getDefaultTableRecordsPerPageSelectOption(): int|string
    {
        $savedOption = session()->get($this->getTablePerPageSessionKey());
        $recordsPerPage = (int) $savedOption;

        return in_array($recordsPerPage, [10, 25, 50], true) ? $recordsPerPage : 50;
    }

    public function updatedPaginaSort(): void
    {
        [$column, $direction] = explode(':', $this->paginaSort, 2) + [null, null];

        if (! in_array($column, ['updated_at', 'nombre_publico'], true) || ! in_array($direction, ['asc', 'desc'], true)) {
            $this->paginaSort = 'updated_at:desc';
            $column = 'updated_at';
            $direction = 'desc';
        }

        $this->sortTable($column, $direction);
    }

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
