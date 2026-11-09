@php
    $livewire = $getLivewire();
    $labels = $livewire::RESEARCH_FIELD_LABELS;
    $job = $livewire->researchJob();
    $state = $job['estado'] ?? null;
    $active = in_array($state, ['buscando', 'investigando'], true);
    $stepIcons = ['pendiente' => '○', 'en_curso' => '…', 'encontrado' => '✓', 'no_encontrado' => '–', 'omitido' => '–', 'error' => '!'];
    $stepNames = ['pendiente' => 'Pendiente', 'en_curso' => 'Buscando', 'encontrado' => 'Encontrado', 'no_encontrado' => 'No encontrado', 'omitido' => 'Omitido', 'error' => 'Con error'];
@endphp
<div class="ct-research" x-show="editorTab === 'datos'" wire:key="business-research" @if ($active) wire:poll.2s="refreshResearch" @elseif ($state === 'lista' && ! $livewire->researchSelectionReady) wire:poll.1s="refreshResearch" @endif>
    <div class="ct-research-head">
        <h3>Buscar negocio en internet</h3>
        <p>Escribe el nombre del negocio, elige el correcto entre los resultados de Google y se buscarán en segundo plano sus reseñas, mapa, web, Instagram, Facebook, TikTok y WhatsApp. Nada se publica solo: la página se guarda como borrador.</p>
    </div>

    @if ($livewire->researchError)
        <div class="ct-research-error" role="alert">{{ $livewire->researchError }}</div>
    @endif

    @if ($job === null)
        <div class="ct-research-form">
            <label class="ct-research-field">
                <span>Nombre del negocio</span>
                <input type="text" autocomplete="off" maxlength="120" placeholder="Ej. Barbería 7E" wire:model="researchName" wire:keydown.enter.prevent="startResearch">
            </label>
            <label class="ct-research-field">
                <span>Ciudad</span>
                <input type="text" autocomplete="off" maxlength="80" placeholder="Ej. Concepción" wire:model="researchCity" wire:keydown.enter.prevent="startResearch">
            </label>
            <button type="button" class="ct-research-button" wire:click="startResearch" wire:loading.attr="disabled" wire:target="startResearch">
                <span wire:loading.remove wire:target="startResearch">Buscar</span>
                <span wire:loading wire:target="startResearch">Iniciando…</span>
            </button>
        </div>
    @else
        <p class="ct-research-note">Búsqueda: <strong>{{ $job['consulta'] }}</strong>@if ($job['ciudad']) · {{ $job['ciudad'] }}@endif</p>

        @if ($active && ! $livewire->researchWorkerAvailable() && now()->diffInSeconds($job['creado']) > 15)
            <div class="ct-research-error" role="alert">El proceso en segundo plano no responde. Pide que se inicie el servicio <code>research-worker</code> de Docker.</div>
        @endif

        @if ($state === 'buscando')
            <div class="ct-research-status" role="status" aria-live="polite">Buscando «{{ $job['consulta'] }}» en Google…</div>
            <div class="ct-research-actions"><button type="button" class="ct-research-button is-secondary" wire:click="cancelResearch">Cancelar</button></div>
        @elseif ($state === 'esperando_eleccion')
            <p class="ct-research-title">Elige el negocio correcto ({{ count($job['candidatos']) }} {{ count($job['candidatos']) === 1 ? 'resultado' : 'resultados' }})</p>
            <ul class="ct-research-list">
                @foreach ($job['candidatos'] as $index => $candidate)
                    <li class="ct-research-candidate" wire:key="candidate-{{ $index }}">
                        <div class="ct-research-row-body">
                            <span class="ct-research-row-title">{{ $candidate['name'] }}@if ($candidate['category'])<span class="ct-research-badge is-existing">{{ $candidate['category'] }}</span>@endif</span>
                            <span class="ct-research-value">{{ $candidate['address'] }}</span>
                            <span class="ct-research-origin">
                                @if ($candidate['rating']) ★ {{ $candidate['rating'] }} {{ $candidate['review_count_label'] }} · @endif
                                <a href="{{ $candidate['maps_url'] }}" target="_blank" rel="noopener noreferrer">Ver en Google Maps</a>
                            </span>
                        </div>
                        <button type="button" class="ct-research-button" wire:click="chooseResearchCandidate({{ $index }})" wire:loading.attr="disabled">Es este</button>
                    </li>
                @endforeach
            </ul>
            <div class="ct-research-actions"><button type="button" class="ct-research-button is-secondary" wire:click="discardResearch">Ninguno, buscar de nuevo</button></div>
        @elseif ($state === 'sin_resultados')
            <div class="ct-research-error" role="alert">Google no encontró negocios con ese nombre. Revisa la ortografía o agrega la ciudad.</div>
            <div class="ct-research-actions"><button type="button" class="ct-research-button" wire:click="discardResearch">Buscar de nuevo</button></div>
        @elseif ($state === 'fallida' || $state === 'cancelada')
            <div class="ct-research-error" role="alert">{{ $state === 'cancelada' ? 'La búsqueda se canceló.' : $job['error'] }}</div>
            <div class="ct-research-actions"><button type="button" class="ct-research-button" wire:click="discardResearch">Buscar de nuevo</button></div>
        @endif

        @if (in_array($state, ['investigando', 'lista'], true))
            <p class="ct-research-title">{{ $job['elegido']['name'] }} <span class="ct-research-origin">{{ $job['elegido']['address'] }}</span></p>
            <ol class="ct-research-steps" aria-label="Avance de la investigación">
                @foreach ($job['pasos'] as $step)
                    <li class="ct-research-step is-{{ $step['estado'] }}" wire:key="step-{{ $step['clave'] }}">
                        <span class="ct-research-step-icon" aria-hidden="true">{{ $stepIcons[$step['estado']] ?? '○' }}</span>
                        <span class="ct-research-row-body">
                            <span class="ct-research-row-title">{{ $step['etiqueta'] }} <span class="ct-research-origin">{{ $stepNames[$step['estado']] ?? '' }}</span></span>
                            @if ($step['detalle'])<span class="ct-research-origin">{{ $step['detalle'] }}</span>@endif
                            @if ($step['estado'] === 'no_encontrado' && $step['busqueda_manual'])
                                <a class="ct-research-origin" href="{{ $step['busqueda_manual'] }}" target="_blank" rel="noopener noreferrer">Buscar manualmente en Google</a>
                            @endif
                            @if ($state === 'lista' && in_array($step['clave'], ['instagram', 'facebook', 'tiktok'], true) && in_array($step['estado'], ['no_encontrado', 'error'], true))
                                <span class="ct-research-manual">
                                    <input type="text" autocomplete="off" maxlength="300" placeholder="Pega el enlace del perfil o su @usuario" aria-label="Perfil de {{ $step['etiqueta'] }}" wire:model="researchManual.{{ $step['clave'] }}" wire:keydown.enter.prevent="useManualProfile('{{ $step['clave'] }}')">
                                    <button type="button" class="ct-research-button is-secondary" wire:click="useManualProfile('{{ $step['clave'] }}')">Usar</button>
                                </span>
                                @if (! empty($livewire->researchManualErrors[$step['clave']]))
                                    <span class="ct-research-origin" role="alert" style="color: #7a271a;">{{ $livewire->researchManualErrors[$step['clave']] }}</span>
                                @endif
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
            @if ($state === 'investigando')
                <div class="ct-research-actions"><button type="button" class="ct-research-button is-secondary" wire:click="cancelResearch">Cancelar</button></div>
            @endif
        @endif

        @if ($state === 'lista')
            <p class="ct-research-title">Datos encontrados</p>
            <ul class="ct-research-list">
                @foreach ($job['campos'] as $field => $data)
                    @continue(! isset($labels[$field]))
                    <li wire:key="field-{{ $field }}">
                        <label class="ct-research-row">
                            <input type="checkbox" wire:model="researchSelected.{{ $field }}">
                            <span class="ct-research-row-body">
                                <span class="ct-research-row-title">
                                    {{ $labels[$field] }}
                                    <span class="ct-research-badge {{ $data['verificado'] ? 'is-verified' : 'is-suggested' }}">{{ $data['verificado'] ? 'Verificado' : 'Sugerido' }}</span>
                                    @if ($livewire->researchFilled[$field] ?? false)
                                        <span class="ct-research-badge is-existing">Reemplaza el valor actual</span>
                                    @endif
                                </span>
                                <span class="ct-research-value">{{ $data['valor'] }}</span>
                                <span class="ct-research-origin">{{ match ($data['origen']) {
                                    'google' => 'De la ficha de Google',
                                    'pagina' => 'Leído de la web del negocio',
                                    'ia' => 'Ordenado con IA y comprobado en la web del negocio',
                                    'busqueda_web' => 'Encontrado por búsqueda web',
                                    'telefono_movil' => 'Deducido del teléfono móvil',
                                    'manual' => 'Ingresado por ti',
                                    default => $data['origen'],
                                } }}</span>
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>
            @if ($job['descartados'] !== [])
                <details class="ct-research-note">
                    <summary>{{ count($job['descartados']) }} dato(s) descartado(s) por no poder comprobarse en la web</summary>
                    <ul>@foreach ($job['descartados'] as $discarded)<li>{{ $discarded }}</li>@endforeach</ul>
                </details>
            @endif
            @foreach ($job['pendientes'] as $pending)
                <p class="ct-research-note">Pendiente: {{ $pending }}</p>
            @endforeach
            <div class="ct-research-actions">
                <button type="button" class="ct-research-button" wire:click="applyResearch" wire:loading.attr="disabled">Aplicar seleccionados al formulario</button>
                <button type="button" class="ct-research-button is-secondary" wire:click="discardResearch">Descartar</button>
            </div>
        @endif
    @endif
</div>
