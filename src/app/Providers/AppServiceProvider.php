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
        //
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
