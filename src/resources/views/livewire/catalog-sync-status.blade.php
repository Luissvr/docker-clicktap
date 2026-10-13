<div class="px-4 py-2 text-sm text-gray-600" @if($busy) wire:poll.5s="refreshStatus" @endif>
    <div class="flex flex-wrap items-center gap-3" role="status" aria-live="polite">
        <span>
            @if($running)
                Sincronización en curso…
            @elseif($busy)
                Sincronización solicitada…
            @elseif($status['completed_at'] ?? null)
                Última sincronización: {{ \Carbon\Carbon::createFromTimestamp($status['completed_at'])->locale('es')->diffForHumans() }}
            @else
                Primera sincronización pendiente
            @endif
        </span>
        <x-filament::button size="xs" color="gray" wire:click="synchronize" wire:loading.attr="disabled" :disabled="$busy">
            Sincronizar ahora
        </x-filament::button>
        <span class="text-xs">Automática cada 20 minutos</span>
    </div>
    @if($status['error'] ?? null)
        <p class="mt-1 text-danger-600">{{ $status['error'] }}</p>
    @endif
    @unless($workerAvailable)
        <p class="mt-1 text-warning-600">La sincronización automática no está disponible. Revisa el servicio de sincronización.</p>
    @endunless
    @if($busy)
        <p class="mt-1 text-xs">Los listados se actualizarán al terminar. Los formularios abiertos conservarán tus cambios; vuelve a abrirlos para cargar la nueva versión.</p>
    @endif
</div>
