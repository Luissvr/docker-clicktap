<?php

use App\Models\Pagina;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/preview/{pagina:slug}', function (Pagina $pagina) {
    return view('paginas.preview', ['pagina' => $pagina->load('enlaces')]);
})->middleware('auth')->name('paginas.preview');
