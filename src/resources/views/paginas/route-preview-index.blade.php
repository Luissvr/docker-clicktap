<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Ruta Concepción · Vistas previas</title>
    <style>
        :root{font-family:Inter,system-ui,sans-serif;color:#172033;background:#f1f5f9}
        *{box-sizing:border-box}body{margin:0;padding:40px 24px}main{max-width:1100px;margin:auto}
        .eyebrow{font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#475569}
        h1{font-size:clamp(28px,5vw,44px);letter-spacing:-.04em;margin:14px 0}h2{font-size:20px;margin:32px 0 16px}
        .intro{line-height:1.6;color:#475569;max-width:760px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
        article{background:white;border:1px solid #dbe3ee;border-radius:16px;padding:22px;display:flex;flex-direction:column;gap:12px}
        .number{font-size:12px;color:#64748b}h3{font-size:19px;line-height:1.35;margin:0}p{margin:0;line-height:1.5}
        .address{color:#475569;font-size:14px;min-height:42px}.status{font-size:12px;color:#166534}.pending{color:#92400e}
        nav{display:flex;gap:16px;align-items:center;margin-top:auto;padding-top:8px}a{font-size:14px;color:#1d4ed8;font-weight:600;text-decoration:none}
        a:hover{text-decoration:underline}.button{background:#0f172a;color:white;border-radius:9px;padding:11px 16px}
        .note{margin-top:28px;font-size:13px;color:#64748b}
    </style>
</head>
<body>
<main>
    <div class="eyebrow">ClickTap · Prospección local</div>
    <h1>Ruta Concepción</h1>
    <p class="intro">{{ count($items) }} páginas en borrador con el diseño de FEFIGAS, Ralph Service y Taller 80s.
        Abre cada propuesta para revisarla antes de la visita. Los logos están pendientes de confirmación con cada local.</p>
    @foreach ([1 => 'Primer lote · visitas 1–5', 2 => 'Segundo lote · visitas 6–15'] as $batch => $title)
        @php($batchItems = array_filter($items, fn ($item) => $batch === 1 ? $item['order'] <= 5 : $item['order'] > 5))
        @if ($batchItems !== [])
            <h2>{{ $title }}</h2>
            <div class="grid">
                @foreach ($batchItems as $item)
                    <article>
                        <div class="number">VISITA {{ str_pad((string) $item['order'], 2, '0', STR_PAD_LEFT) }} · {{ $item['template'] }}</div>
                        <h3>{{ $item['name'] }}</h3>
                        <p class="address">{{ $item['address'] }}</p>
                        <p class="status {{ $item['review_ready'] ? '' : 'pending' }}">
                            {{ $item['review_ready'] ? 'Ficha de Google identificada · reseñas listas' : 'Ficha comercial de Google por confirmar' }}
                        </p>
                        @foreach ($item['verification_notes'] as $note)
                            <p class="status pending">{{ $note }}</p>
                        @endforeach
                        <nav><a class="button" href="{{ $item['file'] }}">Ver propuesta</a><a href="{{ $item['panel_url'] }}">Abrir en panel</a></nav>
                    </article>
                @endforeach
            </div>
        @endif
    @endforeach
    <p class="note">Las propuestas son borradores. Los datos recopilados y sus fuentes están guardados en la ficha interna de cada local.</p>
</main>
</body>
</html>
