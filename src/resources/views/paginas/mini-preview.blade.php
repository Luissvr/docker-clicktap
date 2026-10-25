<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $nombre ?: 'ClickTap' }}</title>
    @if ($googleFontUrl)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="{{ $googleFontUrl }}" rel="stylesheet">
    @endif
    <style>
        :root {
            --background: {{ $colorFondo }};
            --text: {{ $colorTexto }};
            --button: {{ $colorBoton }};
            --button-text: {{ $colorTextoBoton }};
            --accent: {{ $colorAcento }};
            --muted: {{ $colorTexto }};
            --logo-width: {{ $logoWidth }}px;
            --font-family: {!! $fontFamily !!};
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            min-height: 100%;
            background: var(--background);
            color: var(--text);
            font-family: var(--font-family);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        body {
            position: relative;
            padding: 24px 16px 36px;
            text-align: center;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: .12;
            z-index: 0;
        }
        body.theme-default::before {
            background: radial-gradient(circle at 50% -10%, var(--button) 0, transparent 48%);
        }
        body.theme-barber::before {
            background: radial-gradient(circle at 12% 0%, var(--button) 0, transparent 35%),
                        repeating-linear-gradient(135deg, transparent 0 24px, var(--button) 25px 26px, transparent 27px 50px);
            opacity: .14;
        }
        body.theme-floral::before {
            background: radial-gradient(circle at 15% 12%, var(--button) 0 7%, transparent 8%),
                        radial-gradient(circle at 86% 22%, var(--button) 0 10%, transparent 11%),
                        radial-gradient(circle at 72% 88%, var(--button) 0 8%, transparent 9%);
            opacity: .14;
        }
        .content {
            position: relative;
            z-index: 1;
            max-width: 360px;
            margin: 0 auto;
        }
        body.theme-comercio {
            padding: 12px 12px 20px;
            text-align: left;
            background: radial-gradient(circle at 50% -4%, rgba(61, 122, 173, .27), transparent 28rem), linear-gradient(180deg, #0b1b2d, #081321 36rem);
            background-color: #081321;
            color: #f6f9fd;
        }
        html:has(body.theme-comercio) { background: #081321; }
        .theme-comercio .content { max-width: 496px; text-align: left; }
        .theme-comercio .logo-wrap { width: 100%; min-height: 0; margin: 0 auto 12px; }
        .theme-comercio .logo-img:not(.logo-img--round):not(.logo-img--rounded) {
            width: 100%; max-height: none; height: auto; border-radius: 14px;
        }
        .theme-comercio .logo-img--round { width: min(var(--logo-width), 100%); margin: 0 auto; }
        .theme-comercio .logo-img--rounded {
            width: min(var(--logo-width), 100%); height: auto; aspect-ratio: 1; margin: 0 auto;
            border-radius: 16px; background: #fff; padding: 7px; object-fit: contain;
        }
        .theme-comercio h1 {
            color: #f7f9fc; font-family: "Aptos Display", "Segoe UI Variable Display", "Segoe UI", sans-serif;
            font-size: clamp(27px, 6.5vw, 34px); font-weight: 700; line-height: 1.13;
            letter-spacing: -.035em; text-wrap: balance; margin: 0 0 6px; text-align: center;
        }
        .theme-comercio h1::after {
            content: ''; display: block; width: 56px; height: 3px; margin: 8px auto 0;
            border-radius: 999px; background: linear-gradient(90deg, #ee786e, #8bbbd9);
        }
        .theme-comercio .tagline {
            margin: 0 auto 10px; color: #b9cadb; text-align: center; font-size: 15px;
            line-height: 1.45; overflow-wrap: anywhere;
        }
        .theme-comercio .details {
            display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px 9px;
            align-items: start; margin: 10px 0; text-align: left;
        }
        .theme-comercio .detail-section { gap: 8px; min-width: 0; padding: 6px 3px; }
        .theme-comercio .detail-icon { width: 19px; height: 19px; color: #ff8b7f; }
        .theme-comercio .detail-content { min-width: 0; font-size: 13px; color: #f6f9fd; overflow-wrap: anywhere; }
        .theme-comercio .detail-content h2 { margin: 0 0 5px; color: #ff8b7f; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .theme-comercio .detail-content p { margin: 0; white-space: pre-line; }
        .theme-comercio .detail-content a { display: block; color: inherit; text-decoration: none; overflow-wrap: anywhere; }
        .theme-comercio .detail-schedule { grid-column: 1 / -1; }
        .theme-comercio .links { gap: 8px; margin: 10px auto 0; width: 100%; }
        .theme-comercio .link-button { display: flex; justify-content: center; gap: 10px; min-height: 48px; padding: 10px 15px; border: 1px solid rgba(139, 187, 217, .48); border-radius: 14px; background: linear-gradient(115deg, rgba(244, 107, 94, .14), rgba(139, 187, 217, .2)), #122a43; color: #f6f9fd; font-size: 16px; }
        .theme-comercio .link-button:hover { border-color: rgba(244, 139, 127, .78); background: linear-gradient(115deg, rgba(244, 107, 94, .24), rgba(139, 187, 217, .28)), #1b3b59; }
        .theme-comercio .link-button-whatsapp { border-color: rgba(37, 211, 102, .48); background: linear-gradient(135deg, rgba(37, 211, 102, .2), #122a43 86%); }
        .theme-comercio .link-button-whatsapp:hover { border-color: rgba(37, 211, 102, .72); background: linear-gradient(135deg, rgba(37, 211, 102, .29), #173851 86%); }
        .theme-comercio .link-button.link-button-review { position: relative; min-height: 48px; padding-inline: 2.8rem; border-color: rgba(251, 188, 4, .58); background: linear-gradient(115deg, rgba(251, 188, 4, .2), rgba(244, 107, 94, .12)), #122a43; font-size: 14px; }
        .theme-comercio .link-button-review .link-icon { position: absolute; top: 50%; left: 1rem; transform: translateY(-50%); }
        .theme-comercio .link-button-review .link-icon svg { color: #facc15; }
        .theme-comercio .link-label { width: auto; padding: 0; line-height: 1.25; white-space: normal; overflow: visible; text-overflow: clip; }
        .theme-comercio .transfer-logo { width: 32px; height: 32px; object-fit: contain; border-radius: 4px; background: #fff; }
        .theme-comercio .footer-brand { display: none; }
        @media (max-width: 350px) {
            .theme-comercio .details { grid-template-columns: minmax(0, 1fr); gap: 0; }
            .theme-comercio .detail-schedule { grid-column: 1; }
        }
        .badge-preview {
            display: inline-block;
            margin-bottom: 14px;
            padding: 4px 12px;
            border: 1px solid currentColor;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
        }
        .logo-wrap {
            margin: 0 auto 14px;
            width: min(var(--logo-width), 100%);
            min-height: 48px;
        }
        .logo-img {
            width: 100%;
            max-height: 320px;
            height: auto;
            border-radius: 0;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
        }
        .logo-img--round { aspect-ratio: 1; height: var(--logo-width); object-fit: cover; border-radius: 50%; }
        .logo-img--rounded { aspect-ratio: 1; height: var(--logo-width); object-fit: contain; border-radius: 18px; background: #fff; padding: 8px; }
        .logo-placeholder {
            width: 88px;
            height: 88px;
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            background: var(--accent);
            color: var(--button-text);
            margin: 0 auto;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
        }
        h1 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
            line-height: 1.25;
            word-break: break-word;
        }
        .description {
            max-width: 100%;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 14px;
            overflow-wrap: anywhere;
            text-align: justify;
        }
        .description--unbroken { text-justify: inter-character; }
        .details { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; margin: 20px 0; text-align: left; }
        .detail-section { display: flex; align-items: flex-start; gap: 9px; min-width: 0; }
        .detail-icon { flex: none; width: 18px; height: 18px; color: var(--accent); }
        .detail-content { min-width: 0; font-size: 12px; overflow-wrap: anywhere; }
        .detail-content h2 { margin: 0 0 5px; color: var(--accent); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .detail-content p { margin: 0; }
        .detail-content a { display: block; color: inherit; text-decoration: none; }
        .detail-content a + a { margin-top: 4px; }
        .links {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }
        .link-button {
            display: grid;
            grid-template-columns: 1.25rem 1fr;
            align-items: center;
            width: 100%;
            min-height: 48px;
            padding: 10px 14px;
            border: 1px solid var(--button);
            border-radius: 12px;
            background: var(--button);
            color: var(--button-text);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all .15s ease;
            box-sizing: border-box;
        }
        .link-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,.08);
        }
        .link-button--disabled {
            cursor: default;
        }
        .link-icon {
            width: 1.25rem;
            height: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .link-icon svg {
            width: 1.25rem;
            height: 1.25rem;
        }
        .link-label {
            text-align: center;
            padding-right: 1.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .empty-placeholder {
            padding: 18px 12px;
            border: 1px dashed currentColor;
            border-radius: 12px;
            font-size: 12px;
            margin-top: 8px;
        }
        .footer-brand {
            margin-top: 28px;
            padding-top: 14px;
            border-top: 1px solid rgba(128,128,128,.15);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
        }
    </style>
</head>
<body class="theme-{{ $tema }}">
<div class="content">
    <div class="logo-wrap">
        @if ($logoUrl)
            <img @class(['logo-img', 'logo-img--round' => $logoLayout === 'round', 'logo-img--rounded' => $logoLayout === 'rounded']) src="{{ $logoUrl }}" alt="Logo de {{ $nombre }}">
        @else
            <div class="logo-placeholder">
                {{ mb_substr($nombre ?: 'C', 0, 1) }}
            </div>
        @endif
    </div>

    <h1>{{ $nombre ?: 'Nombre del negocio' }}</h1>

    @if ($tema === 'comercio')
        @if ($tagline)
            <p class="tagline">{{ $tagline }}</p>
        @endif
        @if ($direccion || $telefono || $correo || $schedule)
            <div class="details" aria-label="Ubicación y contacto">
                @if ($direccion)
                    <section class="detail-section detail-location" aria-labelledby="detail-location">
                        <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 5.2-8 11-8 11S4 15.2 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.8"/></svg>
                        <div class="detail-content"><h2 id="detail-location">Ubicación</h2><p>{{ $direccion }}</p></div>
                    </section>
                @endif
                @if ($telefono || $correo)
                    <section class="detail-section detail-contact" aria-labelledby="detail-contact">
                        <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 3.1 5.2 2 2 0 0 1 5.1 3h3a2 2 0 0 1 2 1.7l.5 2.8a2 2 0 0 1-.6 1.8L8.2 11a16 16 0 0 0 4.8 4.8l1.7-1.8a2 2 0 0 1 1.8-.6l2.8.5a2 2 0 0 1 1.7 2Z"/></svg>
                        <div class="detail-content"><h2 id="detail-contact">Contacto</h2>
                            @if ($telefono)<a href="tel:{{ preg_replace('/[^\d+]/', '', $telefono) }}">{{ $telefono }}</a>@endif
                            @if ($correo)<a href="mailto:{{ $correo }}">{{ $correo }}</a>@endif
                        </div>
                    </section>
                @endif
                @if ($schedule)
                    <section class="detail-section detail-schedule" aria-labelledby="detail-schedule">
                        <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/></svg>
                        <div class="detail-content"><h2 id="detail-schedule">Horario</h2><p>{{ $schedule }}</p></div>
                    </section>
                @endif
            </div>
        @endif
    @else
        @if ($descripcion)
            <p @class(['description', 'description--unbroken' => preg_match('/\s/u', trim((string) $descripcion)) === 0])>{{ $descripcion }}</p>
        @endif
        <x-paginas.contact-sections :address="$direccion" :phone="$telefono" :email="$correo" />
    @endif

    <nav class="links">
        @forelse ($enlaces as $enlace)
            @php
                $tipo = $enlace['tipo'] ?? 'web';
                $enlaceLabel = ($enlace['etiqueta'] ?? null) ?: (($enlace['nombre'] ?? null) ?: \App\Support\LinkTypeHelper::label($tipo));
                $copyText = !empty($enlace['copiar_texto']) ? ($enlace['texto_copia'] ?? '') : null;
                $url = !empty($enlace['url']) ? $enlace['url'] : null;
                $svg = \App\Support\LinkTypeHelper::iconSvg($tipo, 'w-5 h-5');
            @endphp
            @if ($copyText)
                <button @class(['link-button', 'link-button-review' => $tipo === 'review', 'link-button-whatsapp' => $tipo === 'whatsapp']) type="button" data-copy-text="{{ $copyText }}">
                    <span class="link-icon">
                        @if ($tema === 'comercio' && $tipo === 'transferencia' && $bankLogo)
                            <img class="transfer-logo" src="{{ $bankLogo }}" alt="">
                        @else
                            {!! $svg !!}
                        @endif
                    </span>
                    <span class="link-label">{{ $enlaceLabel }}</span>
                </button>
            @elseif ($url)
                <a @class(['link-button', 'link-button-review' => $tipo === 'review', 'link-button-whatsapp' => $tipo === 'whatsapp']) href="{{ $url }}" target="_blank" rel="noopener noreferrer">
                    <span class="link-icon">{!! $svg !!}</span>
                    <span class="link-label">{{ $enlaceLabel }}</span>
                </a>
            @else
                <span @class(['link-button', 'link-button--disabled', 'link-button-review' => $tipo === 'review', 'link-button-whatsapp' => $tipo === 'whatsapp'])>
                    <span class="link-icon">{!! $svg !!}</span>
                    <span class="link-label">{{ $enlaceLabel }}</span>
                </span>
            @endif
        @empty
            <div class="empty-placeholder">
                Agrega enlaces en el panel y pulsa «Cargar personalización» para visualizarlos aquí.
            </div>
        @endforelse
    </nav>

    @if ($tema !== 'comercio')
        <div class="footer-brand">
            CLICKTAP
        </div>
    @endif
</div>
</body>
</html>
