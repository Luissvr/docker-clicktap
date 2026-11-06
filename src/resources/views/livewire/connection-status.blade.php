@php
    $queue = $snapshot['queue'];
    $level = $snapshot['level'];
    $time = fn (?string $value): string => $value ? \Carbon\Carbon::parse($value)->timezone('America/Santiago')->format('d/m H:i:s') : '—';
    $lastSync = ! empty($snapshot['sync']['completed_at'])
        ? \Carbon\Carbon::createFromTimestamp($snapshot['sync']['completed_at'])->locale('es')->diffForHumans()
        : 'Pendiente';
@endphp
<div
    class="ct-status"
    x-data="{ open: false }"
    x-on:open-connection-panel.window="setTimeout(() => { open = true; $wire.showActivity($event.detail?.pageId ?? null) }, 0)"
    x-on:keydown.escape.window="open = false"
    wire:poll.visible.10s="refreshStatus"
>
    <button
        type="button"
        class="ct-status-button"
        x-on:click="open = ! open; if (open) $wire.showActivity(null)"
        x-bind:aria-expanded="open.toString()"
        aria-haspopup="dialog"
        aria-controls="ct-status-panel"
        title="Conexión: {{ $snapshot['label'] }}"
    >
        <span @class(['ct-status-dot', 'is-ok' => $level === 'ok', 'is-busy' => $level === 'busy', 'is-error' => $level === 'error']) aria-hidden="true"></span>
        <span class="ct-status-button-label">Conexión</span>
        <span class="sr-only">Estado: {{ $snapshot['label'] }}</span>
    </button>

    <div
        id="ct-status-panel"
        x-cloak
        x-show="open"
        style="display:none"
        x-on:click.outside="open = false"
        class="ct-status-panel"
        role="dialog"
        aria-label="Conexión y despliegues"
    >
        <div class="ct-status-panel-head">
            <div>
                <h2>Conexión</h2>
                <p role="status">{{ $snapshot['label'] }} · se actualiza solo.</p>
            </div>
            <button type="button" x-on:click="open = false" aria-label="Cerrar" class="ct-status-close">✕</button>
        </div>

        <div class="ct-status-panel-body">
            <section class="ct-status-section" aria-label="Estado de los servicios">
                <ul class="ct-status-list">
                    <li>
                        <span @class(['ct-status-dot', 'is-ok' => $queue['workerAvailable'], 'is-error' => ! $queue['workerAvailable']]) aria-hidden="true"></span>
                        <div>
                            <strong>{{ $queue['workerAvailable'] ? 'Publicador conectado' : 'Publicador sin señal' }}</strong>
                            <p>{{ $queue['workerHeartbeatAt'] ? 'Última señal: '.$time($queue['workerHeartbeatAt']) : 'Aún no hay una señal con hora registrada.' }}</p>
                        </div>
                    </li>
                    <li>
                        <span @class(['ct-status-dot', 'is-ok' => $queue['pendingCount'] === 0 && empty($queue['running']) && ! $queue['tracking'], 'is-busy' => $queue['pendingCount'] > 0 || ! empty($queue['running']) || $queue['tracking']]) aria-hidden="true"></span>
                        <div>
                            <strong>{{ $queue['pendingCount'] > 0 ? $queue['pendingCount'].' en espera' : 'Sin publicaciones en espera' }}</strong>
                            <p>
                                @if (! empty($queue['running']))Procesando: {{ $queue['running']['name'] ?? 'página' }}.
                                @elseif ($queue['tracking'])Comprobando el despliegue en Cloudflare.
                                @else Nada en curso.@endif
                            </p>
                        </div>
                    </li>
                    @if ($snapshot['failedCount'] > 0)
                        <li>
                            <span class="ct-status-dot is-error" aria-hidden="true"></span>
                            <div>
                                <strong>{{ $snapshot['failedCount'] }} {{ $snapshot['failedCount'] === 1 ? 'página con envío fallido' : 'páginas con envío fallido' }}</strong>
                                <p>Revisa la actividad y reintenta desde la tarjeta de la página.</p>
                            </div>
                        </li>
                    @endif
                    @if ($snapshot['syncEnabled'])
                        <li>
                            <span @class(['ct-status-dot', 'is-ok' => ! $snapshot['syncBusy'] && ! $snapshot['syncError'] && ! empty($snapshot['sync']['completed_at']), 'is-busy' => $snapshot['syncBusy'], 'is-error' => $snapshot['syncError']]) aria-hidden="true"></span>
                            <div class="ct-status-sync">
                                <div>
                                    <strong>{{ $snapshot['syncRunning'] ? 'Sincronizando con Supabase…' : ($snapshot['syncBusy'] ? 'Sincronización solicitada…' : ($snapshot['syncError'] ? 'Error de sincronización' : 'Supabase sincronizado')) }}</strong>
                                    <p>{{ $snapshot['syncError'] ? $snapshot['sync']['error'] : 'Última copia local: '.$lastSync.'. Automática cada 20 min.' }}</p>
                                </div>
                                <button type="button" wire:click="synchronize" wire:loading.attr="disabled" wire:target="synchronize" @disabled($snapshot['syncBusy']) class="ct-status-action">{{ $snapshot['syncBusy'] ? 'Sincronizando…' : 'Sincronizar' }}</button>
                            </div>
                        </li>
                    @endif
                </ul>
            </section>

            <section class="ct-status-section" aria-labelledby="ct-status-activity-title">
                <h3 id="ct-status-activity-title" class="ct-status-subtitle">{{ $activityPageId ? 'Historial de la página' : 'Actividad de despliegues' }}</h3>
                @if ($activityLoaded)
                    @livewire('publication-activity', ['pageId' => $activityPageId], key('publication-activity-'.($activityPageId ?? 'all')))
                @else
                    <p class="ct-status-muted">Cargando la actividad…</p>
                @endif
            </section>
        </div>
    </div>
</div>
