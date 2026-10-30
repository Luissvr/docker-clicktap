<?php

use App\Models\Pagina;
use App\Support\LogoCache;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/preview/{pagina:slug}', function (Pagina $pagina) {
    return view('paginas.preview', ['pagina' => $pagina->load('enlaces')]);
})->middleware(['auth', 'can:view,pagina'])->name('paginas.preview');

Route::get('/media/page-logo/{pagina}/{version}', function (Pagina $pagina, string $version) {
    $cache = app(LogoCache::class);
    abort_unless($cache->url($pagina) !== null && hash_equals($cache->version($pagina), $version), 404);

    try {
        $path = $cache->path($pagina);
    } catch (Throwable $exception) {
        report($exception);
        abort(503, 'La imagen no está disponible temporalmente.');
    }
    abort_if($path === null, 404, 'La imagen ya no está en Supabase.');

    return response()->file($path, [
        'Content-Type' => mime_content_type($path),
        'Cache-Control' => 'private, max-age=86400',
        'X-Content-Type-Options' => 'nosniff',
    ]);
})->middleware(['auth', 'can:view,pagina'])->name('paginas.cached-logo');
