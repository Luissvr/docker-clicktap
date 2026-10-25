<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Vista previa · {{ $pagina->nombre_publico }}</title>
    @php
        $backgroundTheme = in_array($pagina->tema, ['default', 'barber', 'floral'], true)
            ? $pagina->tema
            : 'default';
        $mobilePreview = request()->query('device') === 'mobile';
        $fontKey = $pagina->tipografia ?? 'inter';
        $fontFamily = \App\Support\TypographyHelper::fontFamily($fontKey);
        $googleFontUrl = \App\Support\TypographyHelper::googleFontUrl($fontKey);
        $appearance = \App\Support\PageAppearance::resolve([
            'color_fondo' => $pagina->color_fondo,
            'color_texto' => $pagina->color_texto,
            'color_boton' => $pagina->color_boton,
            'color_texto_boton' => $pagina->color_texto_boton,
        ]);
    @endphp
    @if ($googleFontUrl)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="{{ $googleFontUrl }}" rel="stylesheet">
    @endif
    <style>
        :root {
            --background: {{ $appearance['color_fondo'] }};
            --text: {{ $appearance['color_texto'] }};
            --button: {{ $appearance['color_boton'] }};
            --button-text: {{ $appearance['color_texto_boton'] }};
            --accent: {{ $appearance['color_acento'] }};
            --logo-width: {{ $pagina->logo_width ?: 160 }}px;
            --font-family: {!! $fontFamily !!};
        }
        * { box-sizing: border-box; }
        body { position: relative; margin: 0; min-height: 100vh; background: var(--background); color: var(--text); font-family: var(--font-family); }
        body::before { content: ''; position: fixed; inset: 0; pointer-events: none; opacity: .1; }
        body.theme-default::before { background: radial-gradient(circle at 50% -10%, var(--button) 0, transparent 42%); }
        body.theme-barber::before { background: radial-gradient(circle at 12% 0%, var(--button) 0, transparent 35%), repeating-linear-gradient(135deg, transparent 0 34px, var(--button) 35px 36px, transparent 37px 70px); opacity: .14; }
        body.theme-floral::before { background: radial-gradient(circle at 15% 12%, var(--button) 0 7%, transparent 8%), radial-gradient(circle at 86% 22%, var(--button) 0 10%, transparent 11%), radial-gradient(circle at 72% 88%, var(--button) 0 8%, transparent 9%); opacity: .13; }
        .preview-device { position: relative; z-index: 1; min-height: 100vh; }
        main { position: relative; z-index: 1; width: min(100% - 32px, 520px); margin: 0 auto; padding: 48px 0; text-align: center; }
        .preview-device--mobile { display: grid; place-items: center; padding: 24px; background: #d1d5db; }
        .preview-device--mobile main { width: min(100%, 390px); min-height: min(844px, calc(100vh - 48px)); max-height: calc(100vh - 48px); overflow-y: auto; margin: 0; padding: 44px 18px 36px; border: 10px solid #111827; border-radius: 36px; background: var(--background); box-shadow: 0 20px 50px rgb(15 23 42 / .3); }
        .preview { margin-bottom: 28px; padding: 10px 14px; border: 1px solid currentColor; border-radius: 999px; display: inline-block; font-size: 13px; }
        .logo-img { display: block; width: min(var(--logo-width), 100%); max-height: 320px; height: auto; margin: 0 auto; object-fit: contain; }
        .logo-img--round { aspect-ratio: 1; height: var(--logo-width); object-fit: cover; border-radius: 50%; }
        .logo-img--rounded { aspect-ratio: 1; height: var(--logo-width); object-fit: contain; border-radius: 18px; background: #fff; padding: 8px; }
        h1 { margin: 20px 0 8px; font-size: 32px; }
        .description { max-width: 100%; line-height: 1.55; text-align: justify; overflow-wrap: anywhere; white-space: pre-line; }
        .description--unbroken { text-justify: inter-character; }
        .details { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 24px; margin: 28px 0; text-align: left; }
        .detail-section { display: flex; align-items: flex-start; gap: 12px; min-width: 0; }
        .detail-icon { flex: none; width: 21px; height: 21px; color: var(--accent); }
        .detail-content { min-width: 0; overflow-wrap: anywhere; }
        .detail-content h2 { margin: 0 0 8px; color: var(--accent); font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .detail-content p { margin: 0; }
        .detail-content a { display: block; color: inherit; text-decoration: none; }
        .detail-content a + a { margin-top: 6px; }
        .links { display: grid; gap: 12px; margin-top: 28px; }
        .link-button { display: grid; grid-template-columns: 1.25rem 1fr; align-items: center; width: 100%; min-height: 58px; padding: 14px 18px; border: 1px solid var(--button); border-radius: 14px; background: var(--button); color: var(--button-text); text-decoration: none; font-weight: 700; }
        .links button { font: inherit; cursor: pointer; }
        .link-button--disabled { opacity: .7; }
        .link-icon { width: 1.25rem; height: 1.25rem; }
        .link-icon--whatsapp { color: #25d366; }
        .link-icon--instagram { color: #e1306c; }
        .link-icon--facebook { color: #1877f2; }
        .link-icon--review { color: #facc15; }
        .link-label { text-align: center; padding-right: 1.25rem; }
    </style>
</head>
<body class="theme-{{ $backgroundTheme }}">
<div class="preview-device{{ $mobilePreview ? ' preview-device--mobile' : '' }}">
<main>
    <span class="preview">Vista previa del panel</span>

    @if ($pagina->logo_path)
        @php
            $logoUrl = str_starts_with($pagina->logo_path, 'http')
                ? $pagina->logo_path
                : ($pagina->logo_disk === 'public'
                    ? '/storage/'.ltrim($pagina->logo_path, '/')
                    : \Illuminate\Support\Facades\Storage::disk($pagina->logo_disk)->temporaryUrl($pagina->logo_path, now()->addMinutes(5)));
        @endphp
        <img @class(['logo-img', 'logo-img--round' => $pagina->logo_layout === 'round', 'logo-img--rounded' => $pagina->logo_layout === 'rounded']) src="{{ $logoUrl }}" alt="Logo de {{ $pagina->nombre_publico }}">
    @endif

    <h1>{{ $pagina->nombre_publico }}</h1>

    @if ($pagina->descripcion)
        <p @class(['description', 'description--unbroken' => preg_match('/\s/u', trim((string) $pagina->descripcion)) === 0])>{{ $pagina->descripcion }}</p>
    @endif

    <x-paginas.contact-sections :address="$pagina->direccion_publica" :phone="$pagina->telefono_publico" :email="$pagina->correo_publico" />

    <nav class="links" aria-label="Enlaces de {{ $pagina->nombre_publico }}">
        @foreach ($pagina->enlaces->where('activo', true) as $enlace)
            @php
                $enlaceLabel = $enlace->etiqueta ?: ($enlace->nombre ?: match ($enlace->tipo) {
                    'transferencia' => 'Datos de transferencia',
                    'whatsapp' => 'WhatsApp',
                    'instagram' => 'Instagram',
                    'facebook' => 'Facebook',
                    'review' => 'Escribir una reseña',
                    'location' => 'Cómo llegar',
                    'web' => 'Página web',
                    default => 'Enlace',
                });
            @endphp
            @if ($enlace->tipo === 'transferencia' || ($enlace->tipo !== 'review' && $enlace->copiar_texto && $enlace->texto_copia))
                <button class="link-button" type="button" data-copy-text="{{ $enlace->texto_copia }}"><x-paginas.link-icon :type="$enlace->tipo" /><span class="link-label">{{ $enlaceLabel }}</span></button>
            @elseif (($enlace->tipo !== 'review' && $enlace->copiar_texto) || ! $enlace->url)
                <span class="link-button link-button--disabled"><x-paginas.link-icon :type="$enlace->tipo" /><span class="link-label">{{ $enlaceLabel }}</span></span>
            @else
                <a class="link-button" href="{{ $enlace->url }}" target="_blank" rel="noopener noreferrer"><x-paginas.link-icon :type="$enlace->tipo" /><span class="link-label">{{ $enlaceLabel }}</span></a>
            @endif
        @endforeach
    </nav>
</main>
</div>
<script>
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy-text]');
        if (!button || !button.dataset.copyText.trim()) return;

        await navigator.clipboard.writeText(button.dataset.copyText);
        const originalLabel = button.textContent;
        button.textContent = 'Copiado';
        setTimeout(() => { button.textContent = originalLabel; }, 1500);
    });
</script>
</body>
</html>
