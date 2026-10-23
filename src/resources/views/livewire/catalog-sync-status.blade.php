<div class="ct-sidebar-sync" @if($busy) wire:poll.5s="refreshStatus" @else wire:poll.60s="refreshStatus" @endif>
    @php
        $hasError = ! $busy && ! empty($status['error']);
        $lastSyncTime = ! empty($status['completed_at'])
            ? \Carbon\Carbon::createFromTimestamp($status['completed_at'])->locale('es')->diffForHumans()
            : 'Pendiente';
        $lastSync = 'Última sincronización: ' . $lastSyncTime;
        $heading = match (true) {
            $running => 'Sincronizando…',
            $busy => 'Sincronización solicitada…',
            $hasError => 'Error de sincronización',
            default => 'Sincronización',
        };
    @endphp

    <button
        type="button"
        wire:click="synchronize"
        wire:loading.attr="disabled"
        wire:target="synchronize"
        @disabled($busy)
        aria-label="Sincronizar ahora"
        title="{{ $heading }} · {{ $hasError ? $status['error'] : $lastSync }} · Automática cada 20 min"
        class="ct-sync-button"
    >
        <span @class([
            'ct-sync-dot',
            'ct-sync-dot-busy' => $busy,
            'ct-sync-dot-error' => $hasError,
            'ct-sync-dot-ready' => ! $busy && ! $hasError && ! empty($status['completed_at']),
        ]) aria-hidden="true"></span>
        <svg wire:loading.class="animate-spin" wire:target="synchronize" @class([
            'ct-sync-icon',
            'animate-spin' => $busy,
        ]) aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
        </svg>
        <span class="ct-sync-button-label">{{ $busy ? 'Sincronizando…' : 'Sincronizar' }}</span>
    </button>

    <div class="ct-sync-summary" role="status" aria-label="{{ $heading }}. {{ $lastSync }}" title="{{ $hasError ? $status['error'] : $lastSync }}">
        <span class="ct-sync-detail">{{ $busy || $hasError ? $heading : 'Última: ' . $lastSyncTime }}</span>
    </div>
</div>
