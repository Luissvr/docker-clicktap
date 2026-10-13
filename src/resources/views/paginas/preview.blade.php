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
    @endphp
    <style>
        :root {
            --background: {{ $pagina->color_fondo }};
            --text: {{ $pagina->color_texto }};
            --button: {{ $pagina->color_boton }};
            --button-text: {{ $pagina->color_texto_boton }};
        }
        * { box-sizing: border-box; }
        body { position: relative; margin: 0; min-height: 100vh; background: var(--background); color: var(--text); font-family: ui-sans-serif, system-ui, sans-serif; }
        body::before { content: ''; position: fixed; inset: 0; pointer-events: none; opacity: .1; }
        body.theme-default::before { background: radial-gradient(circle at 50% -10%, var(--button) 0, transparent 42%); }
        body.theme-barber::before { background: radial-gradient(circle at 12% 0%, var(--button) 0, transparent 35%), repeating-linear-gradient(135deg, transparent 0 34px, var(--button) 35px 36px, transparent 37px 70px); opacity: .14; }
        body.theme-floral::before { background: radial-gradient(circle at 15% 12%, var(--button) 0 7%, transparent 8%), radial-gradient(circle at 86% 22%, var(--button) 0 10%, transparent 11%), radial-gradient(circle at 72% 88%, var(--button) 0 8%, transparent 9%); opacity: .13; }
        .preview-device { position: relative; z-index: 1; min-height: 100vh; }
        main { position: relative; z-index: 1; width: min(100% - 32px, 520px); margin: 0 auto; padding: 48px 0; text-align: center; }
        .preview-device--mobile { display: grid; place-items: center; padding: 24px; background: #d1d5db; }
        .preview-device--mobile main { width: min(100%, 390px); min-height: min(844px, calc(100vh - 48px)); max-height: calc(100vh - 48px); overflow-y: auto; margin: 0; padding: 44px 18px 36px; border: 10px solid #111827; border-radius: 36px; background: var(--background); box-shadow: 0 20px 50px rgb(15 23 42 / .3); }
        .preview { margin-bottom: 28px; padding: 10px 14px; border: 1px solid currentColor; border-radius: 999px; display: inline-block; font-size: 13px; opacity: .7; }
        img { display: block; width: 128px; height: 128px; margin: 0 auto; border-radius: 50%; object-fit: cover; }
        h1 { margin: 20px 0 8px; font-size: 32px; }
        .description { line-height: 1.55; opacity: .85; }
        .contact { margin: 24px 0; display: grid; gap: 6px; font-size: 15px; }
        .links { display: grid; gap: 12px; margin-top: 28px; }
        .link-button { display: grid; grid-template-columns: 1.25rem 1fr; align-items: center; width: 100%; min-height: 58px; padding: 14px 18px; border: 1px solid var(--button); border-radius: 14px; background: transparent; color: var(--button); text-decoration: none; font-weight: 700; }
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
        <img src="{{ $logoUrl }}" alt="Logo de {{ $pagina->nombre_publico }}">
    @endif

    <h1>{{ $pagina->nombre_publico }}</h1>

    @if ($pagina->descripcion)
        <p class="description">{{ $pagina->descripcion }}</p>
    @endif

    <div class="contact">
        @if ($pagina->direccion_publica)<span>{{ $pagina->direccion_publica }}</span>@endif
        @if ($pagina->telefono_publico)<a href="tel:{{ $pagina->telefono_publico }}">{{ $pagina->telefono_publico }}</a>@endif
        @if ($pagina->correo_publico)<a href="mailto:{{ $pagina->correo_publico }}">{{ $pagina->correo_publico }}</a>@endif
    </div>

    <nav class="links" aria-label="Enlaces de {{ $pagina->nombre_publico }}">
        @foreach ($pagina->enlaces->where('activo', true) as $enlace)
            @php
                $enlaceLabel = $enlace->etiqueta ?: match ($enlace->tipo) {
                    'transferencia' => 'Datos de transferencia',
                    default => 'Enlace',
                };
            @endphp
            @if ($enlace->copiar_texto && $enlace->texto_copia)
                <button class="link-button" type="button" data-copy-text="{{ $enlace->texto_copia }}"><x-paginas.link-icon :type="$enlace->tipo" /><span class="link-label">{{ $enlaceLabel }}</span></button>
            @elseif ($enlace->copiar_texto || ! $enlace->url)
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
        if (!button) return;

        await navigator.clipboard.writeText(button.dataset.copyText);
        const originalLabel = button.textContent;
        button.textContent = 'Copiado';
        setTimeout(() => { button.textContent = originalLabel; }, 1500);
    });
</script>
</body>
</html>
