@php
    $activity = $running ?? $pending ?? $trackingTask ?? $result;
    $events = $activity['events'] ?? [];
    $showTracking = $trackingTask !== null && ($activity['page_id'] ?? null) === $trackingTask['page_id'];
    $showResult = $result !== null && ($activity['page_id'] ?? null) === $result['page_id'] && ! $running && ! $pending && ! $showTracking;
    $time = fn (?string $value): string => $value ? \Carbon\Carbon::parse($value)->timezone('America/Santiago')->format('d/m H:i:s') : '—';
@endphp

<section wire:poll.visible.5s class="rounded-xl border border-[#DCE0D5] bg-white p-4 sm:p-5" aria-label="Comunicación con el servidor">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-[#101010]">Comunicación con el servidor</h2>
            <p class="mt-1 text-sm text-[#596052]">Llegada de solicitudes y avances reales del worker. Horas de Chile.</p>
        </div>
        <span @class([
            'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold',
            'bg-[#F2F9E9] text-[#375500]' => $workerAvailable,
            'bg-[#FFF8DE] text-[#854D0E]' => ! $workerAvailable,
        ])>
            <span @class(['h-2 w-2 rounded-full', 'bg-[#5B8F00]' => $workerAvailable, 'bg-[#D59A00]' => ! $workerAvailable]) aria-hidden="true"></span>
            {{ $workerAvailable ? 'Worker conectado' : 'Sin señal del worker' }}
        </span>
    </div>

    <p class="mt-3 text-xs text-[#596052]">
        {{ $workerHeartbeatAt ? 'Última señal: '.$time($workerHeartbeatAt) : 'Aún no hay una señal con hora registrada.' }}
        @if ($pendingCount > 0)
            · {{ $pendingCount }} {{ $pendingCount === 1 ? 'solicitud en espera' : 'solicitudes en espera' }}
        @endif
    </p>

    @if ($activity)
        <div class="mt-4 rounded-lg border border-[#DCE0D5] bg-[#FAFBF8] p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-[#101010]">{{ $activity['name'] ?? 'Página' }}</h3>
                <span class="text-xs font-medium text-[#596052]">{{ ($activity['type'] ?? 'publish') === 'withdraw' ? 'Retirada' : 'Publicación' }}</span>
            </div>

            <ol class="mt-4 space-y-3" aria-label="Recorrido de la solicitud">
                @if ($events === [] && ! empty($activity['requested_at']))
                    <li class="flex gap-3 text-sm"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[#5B8F00]"></span><div><strong class="font-medium text-[#101010]">Solicitud recibida por el servidor</strong><span class="ml-2 text-xs text-[#596052]">{{ $time($activity['requested_at']) }}</span></div></li>
                @endif
                @foreach ($events as $event)
                    <li class="flex gap-3 text-sm">
                        <span @class(['mt-1.5 h-2 w-2 shrink-0 rounded-full', 'bg-[#B70000]' => in_array($event['stage'] ?? '', ['failed', 'fallida', 'retirada_fallida'], true), 'bg-[#5B8F00]' => ! in_array($event['stage'] ?? '', ['failed', 'fallida', 'retirada_fallida'], true)]) aria-hidden="true"></span>
                        <div><span class="font-medium text-[#101010]">{{ $event['message'] ?? 'Avance registrado' }}</span><span class="ml-2 whitespace-nowrap text-xs text-[#596052]">{{ $time($event['at'] ?? null) }}</span></div>
                    </li>
                @endforeach
            </ol>

            @if ($running)
                <p class="mt-4 rounded-lg bg-[#EFF6DF] px-3 py-2 text-sm font-medium text-[#375500]">Procesando: {{ $running['stage_message'] ?? 'El worker está trabajando' }}.</p>
            @elseif ($pending)
                <p class="mt-4 rounded-lg bg-[#FFF8DE] px-3 py-2 text-sm font-medium text-[#854D0E]">En cola. {{ $workerAvailable ? 'El worker la tomará en breve.' : 'La solicitud se conserva hasta que vuelva el worker.' }}</p>
            @elseif ($showTracking)
                <p class="mt-4 rounded-lg bg-[#EFF6DF] px-3 py-2 text-sm font-medium text-[#375500]">Repositorio actualizado. Comprobando Cloudflare y el acceso público.</p>
                <p class="mt-2 text-xs text-[#596052]">{{ $trackingTask['attempts'] }} {{ $trackingTask['attempts'] === 1 ? 'comprobación realizada' : 'comprobaciones realizadas' }}{{ ! empty($trackingTask['last_checked_at']) ? ' · Última: '.$time($trackingTask['last_checked_at']) : ' · Esperando la primera comprobación' }}</p>
            @elseif ($showResult)
                <p @class(['mt-4 rounded-lg px-3 py-2 text-sm font-medium', 'bg-[#FFF1F1] text-[#B70000]' => in_array($result['state'] ?? '', ['fallida', 'retirada_fallida', 'interrumpida'], true), 'bg-[#EFF6DF] text-[#375500]' => ! in_array($result['state'] ?? '', ['fallida', 'retirada_fallida', 'interrumpida'], true)])>Último resultado: {{ $result['message'] ?? str_replace('_', ' ', $result['state'] ?? '') }}</p>
            @endif
        </div>
    @else
        <p class="mt-4 rounded-lg border border-dashed border-[#DCE0D5] px-4 py-5 text-sm text-[#596052]">Aún no hay solicitudes de publicación o retirada. Cuando envíes una, verás aquí su recorrido.</p>
    @endif

    @if ($tracking && ! $showTracking)
        <p class="mt-3 text-sm text-[#596052]">Hay otra página esperando la comprobación de Cloudflare.</p>
    @endif
    @unless ($cloudflareConfigured)
        <p class="mt-3 text-sm text-[#854D0E]">El seguimiento de Cloudflare aún no está configurado. El envío al repositorio no confirma el acceso público.</p>
    @endunless
</section>
