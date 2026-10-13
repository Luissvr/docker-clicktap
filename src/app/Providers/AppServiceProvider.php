<?php

namespace App\Providers;

use App\Support\CatalogConnection;
use App\Support\CatalogModelSynth;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (CatalogConnection::enabled()) {
            CatalogConnection::configure();
        }
        Livewire::propertySynthesizer(CatalogModelSynth::class);
    }
}
