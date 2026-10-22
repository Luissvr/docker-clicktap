<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Resumen';

    protected static ?string $navigationGroup = 'Inicio';

    protected static ?string $navigationLabel = 'Resumen';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.dashboard';
}
