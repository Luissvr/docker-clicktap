<?php

namespace App\Providers;

use App\Support\CatalogConnection;
use App\Support\CatalogModelSynth;
use Filament\Forms\Components\Select;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // CLI maintenance and PHP-FPM must not touch compiled files owned by another UID.
        if (function_exists('posix_geteuid')) {
            $base = config('view.compiled_base', config('view.compiled')) ?: storage_path('framework/views');
            $path = rtrim($base, '/').'/php-'.posix_geteuid();
            if (! is_dir($path) && ! @mkdir($path, 0775, true) && ! is_dir($path)) {
                throw new \RuntimeException('No se puede crear el directorio de vistas compiladas: '.$path);
            }
            config(['view.compiled_base' => $base, 'view.compiled' => $path]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Select::configureUsing(fn (Select $select): Select => $select->native(false));

        if (CatalogConnection::enabled()) {
            CatalogConnection::configure();
        }
        Livewire::propertySynthesizer(CatalogModelSynth::class);
    }
}
