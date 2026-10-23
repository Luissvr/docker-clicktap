<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ConfiguracionPrecios extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Configuración de precios';

    protected static ?string $title = 'Configuración de precios';

    protected static ?string $slug = 'configuracion-precios';

    protected static ?int $navigationSort = 9;

    protected static string $view = 'filament.pages.placeholder';
}
