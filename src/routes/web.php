<?php

use App\Models\Pagina;
use App\Support\LogoCache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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
        try {
            return redirect()->away(Storage::disk('supabase')->temporaryUrl($pagina->logo_path, now()->addMinutes(5)))
                ->header('Cache-Control', 'private, no-store');
        } catch (Throwable) {
            abort(503, 'La imagen no está disponible temporalmente.');
        }
    }
    abort_if($path === null, 404, 'La imagen ya no está en Supabase.');

    return response()->file($path, [
        'Content-Type' => mime_content_type($path),
        'Content-Disposition' => 'inline; filename="'.$pagina->slug.'.'.strtolower(pathinfo($pagina->logo_path, PATHINFO_EXTENSION)).'"',
        'Cache-Control' => 'private, max-age=86400',
        'X-Content-Type-Options' => 'nosniff',
    ]);
})->middleware(['auth', 'can:view,pagina'])->name('paginas.cached-logo');
