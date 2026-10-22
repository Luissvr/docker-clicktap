<div class="ct-clientes-toolbar">
    @if (\App\Filament\Resources\ClienteResource::canCreate())
        <x-filament::button
            tag="a"
            :href="\App\Filament\Resources\ClienteResource::getUrl('create')"
            icon="heroicon-o-plus"
            wire:navigate
            class="ct-create-cliente"
        >
            Crear cliente
        </x-filament::button>
    @endif
</div>
