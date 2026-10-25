@php
    $isResourceDetail = $this instanceof \Filament\Resources\Pages\Page
        && ! ($this instanceof \Filament\Resources\Pages\ListRecords);
    $heading = $this->getHeading();
    $headerHeading = $heading;
    $contentHeading = $heading;

    if ($isResourceDetail) {
        $resource = $this::getResource();
        $backLabel = 'Volver a ' . ucfirst($resource::getPluralModelLabel());
        $headerHeading = match (true) {
            $this instanceof \Filament\Resources\Pages\EditRecord => 'Editar ' . $resource::getModelLabel(),
            $this instanceof \Filament\Resources\Pages\CreateRecord => 'Crear ' . $resource::getModelLabel(),
            $this instanceof \Filament\Resources\Pages\ViewRecord => 'Ver ' . $resource::getModelLabel(),
            default => ucfirst($resource::getPluralModelLabel()),
        };

        if ($this instanceof \Filament\Resources\Pages\EditRecord || $this instanceof \Filament\Resources\Pages\ViewRecord) {
            $contentHeading = $this->getRecordTitle();
        }
    }
@endphp

<template x-data="{}" x-teleport="#ct-page-title">
    @if ($isResourceDetail)
        <p class="ct-page-heading">{{ $headerHeading }}</p>
    @else
        <h1 class="ct-page-heading">{{ $headerHeading }}</h1>
    @endif
</template>

@if ($isResourceDetail)
    <div class="ct-content-heading">
        @if ($resource::hasPage('index'))
            <a href="{{ $resource::getUrl('index') }}" class="ct-page-back" wire:navigate>
                <x-filament::icon icon="heroicon-o-arrow-left" class="ct-page-back-icon" />
                <span>{{ $backLabel }}</span>
            </a>
            @if (($this instanceof \App\Filament\Resources\PaginaResource\Pages\CreatePagina || $this instanceof \App\Filament\Resources\PaginaResource\Pages\EditPagina) && $this->ventaId)
                <a href="{{ \App\Filament\Pages\Ventas::getUrl(['venta' => $this->ventaId]) }}" class="ct-page-back" wire:navigate>
                    <x-filament::icon icon="heroicon-o-arrow-left" class="ct-page-back-icon" />
                    <span>Volver a la venta</span>
                </a>
            @endif
        @endif
        @unless ($this instanceof \App\Filament\Resources\PaginaResource\Pages\CreatePagina)
            <h1 class="ct-content-title">{{ $contentHeading }}</h1>
        @endunless
    </div>
@endif
