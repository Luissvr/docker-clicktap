<div class="ct-activity-content" wire:poll.visible.3s>
    @php
        $syncActive = ! empty($sync['running_since']);
        $syncError = ! empty($sync['error']);
        $publishActive = ! empty($publicationStatus['running']) || ! empty($publicationStatus['pending']);
        $cloudflareActive = ! empty($publicationStatus['tracking']);
        $latestState = $latestOperation['state'] ?? null;
    @endphp
    <section class="ct-connection-map" aria-label="Comunicación entre servicios">
        <div class="ct-connection-heading">
            <h3>Comunicación entre servicios</h3>
            <p>Las líneas se mueven mientras hay una operación registrada. El envío y el despliegue son etapas distintas.</p>
        </div>
        <div class="ct-connection-flow">
            <div class="ct-connection-node">
                <span class="ct-connection-icon ct-connection-icon-supabase" aria-hidden="true"><img src="{{ asset('images/supabase-icon.svg') }}" alt="" width="23" height="24"></span>
                <div><strong>Supabase</strong><p>{{ $syncError ? 'Error al actualizar la copia local' : ($syncActive ? 'Sincronizando catálogo' : (! empty($sync['completed_at']) ? 'Última copia local disponible' : 'Sin sincronización confirmada')) }}</p>
                    @if (! empty($sync['completed_at']))<small>Última copia: {{ \Carbon\Carbon::createFromTimestamp($sync['completed_at'])->timezone('America/Santiago')->format('d/m/Y H:i') }}</small>@endif
                </div>
                @if ($supabaseUrl)<a href="{{ $supabaseUrl }}" target="_blank" rel="noopener noreferrer">Abrir servicio</a>@else<small>Enlace no configurado</small>@endif
            </div>
            <div @class(['ct-connection-link', 'is-active' => $syncActive && ! $syncError, 'is-error' => $syncError])><span>{{ $syncActive ? 'Guardando y replicando' : ($syncError ? 'Sincronización con error' : 'Guardado y réplica') }}</span></div>
            <div class="ct-connection-node ct-connection-node-server">
                <span class="ct-connection-icon ct-connection-icon-server" aria-hidden="true"><x-heroicon-o-server-stack class="h-6 w-6" /></span>
                <div><strong>Servidor ClickTap</strong><p>{{ $publishActive ? 'Procesando solicitud de publicación' : (! empty($publicationStatus['workerAvailable']) ? 'Publicador con señal reciente' : 'Publicador sin señal reciente') }}</p></div>
            </div>
            <div @class(['ct-connection-link', 'is-active' => $publishActive])><span>{{ $publishActive ? 'Enviando al repositorio' : 'Publicación' }}</span></div>
            <div class="ct-connection-node">
                <span class="ct-connection-icon ct-connection-icon-repo" aria-hidden="true">⌘</span>
                <div><strong>Repositorio y compilación</strong><p>{{ $publishActive ? 'Operación en curso; consulta el detalle' : (! empty($latestOperation['commit']) ? 'Commit enviado; despliegue por verificar' : 'Sin envío activo') }}</p></div>
            </div>
            <div @class(['ct-connection-link', 'is-active' => $cloudflareActive, 'is-error' => in_array($latestState, ['fallida', 'retirada_fallida'], true)])><span>{{ $cloudflareActive ? 'Comprobando despliegue' : 'Despliegue' }}</span></div>
            <div class="ct-connection-node">
                <span class="ct-connection-icon ct-connection-icon-cloudflare" aria-hidden="true"><x-heroicon-o-cloud class="h-6 w-6" /></span>
                <div><strong>Cloudflare</strong><p>{{ $cloudflareActive ? 'Esperando confirmación del despliegue' : (in_array($latestState, ['publicada', 'retirada'], true) ? 'Resultado verificado' : 'Sin despliegue verificado') }}</p></div>
                @if ($cloudflareUrl)<a href="{{ $cloudflareUrl }}" target="_blank" rel="noopener noreferrer">Abrir servicio</a>@else<small>Enlace no configurado</small>@endif
            </div>
        </div>
    </section>
    <h3 class="ct-activity-list-title">Operaciones registradas</h3>
    @forelse ($activity as $operation)
        <article class="ct-activity-entry">
            <div class="ct-activity-entry-head">
                <strong>{{ $operation['name'] ?? 'Página' }}</strong>
                <span>{{ str_replace('_', ' ', $operation['state'] ?? 'pendiente') }}</span>
            </div>
            <p>{{ ($operation['type'] ?? 'publish') === 'withdraw' ? 'Retirada del sitio' : 'Publicación en producción' }} · Solicitada {{ ! empty($operation['requested_at']) ? \Carbon\Carbon::parse($operation['requested_at'])->timezone('America/Santiago')->format('d/m/Y H:i:s') : 'sin fecha' }}</p>
            @if (! empty($operation['commit']))<p>Commit: <code>{{ $operation['commit'] }}</code></p>@endif
            @if (isset($operation['revision']))<p>Revisión de página: {{ $operation['revision'] }}</p>@endif
            @if (! empty($operation['deployment_id']))<p>Despliegue: <code>{{ $operation['deployment_id'] }}</code> · {{ $operation['deployment_status'] ?? 'estado pendiente' }}</p>@endif
            @if (! empty($operation['public_url']))<p>URL verificada: <a href="{{ $operation['public_url'] }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $operation['public_url'] }}</a> @if (isset($operation['http_status']))· HTTP {{ $operation['http_status'] }}@endif</p>@endif
            @if (! empty($operation['message']))<p>{{ $operation['message'] }}</p>@endif
            @if (! empty($operation['diagnostic']))<p class="ct-activity-diagnostic"><strong>Detalle del error:</strong> {{ $operation['diagnostic'] }}</p>@endif
            <ol>
                @foreach ($operation['events'] ?? [] as $event)
                    <li><time>{{ ! empty($event['at']) ? \Carbon\Carbon::parse($event['at'])->timezone('America/Santiago')->format('H:i:s') : '—' }}</time><span>{{ $event['message'] ?? 'Evento registrado' }}</span></li>
                @endforeach
            </ol>
        </article>
    @empty
        <p>{{ $pageId ? 'Aún no hay operaciones registradas para esta página.' : 'Aún no hay operaciones registradas.' }}</p>
    @endforelse
</div>
