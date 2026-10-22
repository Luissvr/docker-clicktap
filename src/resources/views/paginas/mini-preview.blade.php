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

    @if ($descripcion)
        <p @class(['description', 'description--unbroken' => preg_match('/\s/u', trim((string) $descripcion)) === 0])>{{ $descripcion }}</p>
    @endif

    <x-paginas.contact-sections :address="$direccion" :phone="$telefono" :email="$correo" />

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
                <button class="link-button" type="button" data-copy-text="{{ $copyText }}">
                    <span class="link-icon">{!! $svg !!}</span>
                    <span class="link-label">{{ $enlaceLabel }}</span>
                </button>
            @elseif ($url)
                <a class="link-button" href="{{ $url }}" target="_blank" rel="noopener noreferrer">
                    <span class="link-icon">{!! $svg !!}</span>
                    <span class="link-label">{{ $enlaceLabel }}</span>
                </a>
            @else
                <span class="link-button link-button--disabled">
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

    <div class="footer-brand">
        CLICKTAP
    </div>
</div>
<script>
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy-text]');
        if (!button) return;
        try {
            await navigator.clipboard.writeText(button.dataset.copyText);
            const labelSpan = button.querySelector('.link-label');
            const originalLabel = labelSpan ? labelSpan.textContent : button.textContent;
            if (labelSpan) labelSpan.textContent = '¡Copiado!';
            setTimeout(() => { if (labelSpan) labelSpan.textContent = originalLabel; }, 1500);
        } catch (e) {}
    });
</script>
</body>
</html>
