<x-filament-panels::page>
    <x-filament::section heading="Bienvenido a ClickTap" description="Elige una sección para continuar.">
        <div class="flex flex-wrap gap-3">
            <x-filament::button tag="a" :href="\App\Filament\Resources\PaginaResource::getUrl()">
                Páginas
            </x-filament::button>
            <x-filament::button tag="a" color="gray" :href="\App\Filament\Resources\LocalNegocioResource::getUrl()">
                Locales
            </x-filament::button>
            <x-filament::button tag="a" color="gray" :href="\App\Filament\Resources\ClienteResource::getUrl()">
                Clientes
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
