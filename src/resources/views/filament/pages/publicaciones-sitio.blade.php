<x-filament-panels::page>
    <section class="rounded-xl border border-gray-200 bg-white p-6 space-y-4"
        @if ($pending || $running) wire:poll.visible.5s @endif aria-live="polite">
        <h2 class="text-lg font-semibold">Publicación del sitio</h2>
        @if ($running)
            <p>Enviando las páginas activas al repositorio… Puedes seguir trabajando.</p>
        @elseif ($pending)
            <p>Publicación pendiente: {{ $pending['name'] }}.</p>
            @unless ($workerAvailable)
                <p class="text-amber-800">El procesador está desconectado. La solicitud se conserva y se atenderá cuando vuelva a estar disponible.</p>
            @endunless
        @endif
        @if ($result)
            <p class="font-medium">Último resultado: {{ ucfirst($result['state']) }}</p>
            <p>{{ $result['message'] }}</p>
            @if (!empty($result['commit']))
                <p class="text-sm break-all">Commit: {{ $result['commit'] }}</p>
            @endif
        @elseif (!$pending && !$running)
            <p>No hay solicitudes recientes. Puedes publicar desde las acciones de una página activa.</p>
        @endif
        <x-filament::button color="gray" wire:click="$refresh">Actualizar estado</x-filament::button>
        <a href="{{ \App\Filament\Resources\PaginaResource::getUrl() }}" wire:navigate class="block underline">Volver a páginas</a>
    </section>
</x-filament-panels::page>
