<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class SolicitudesCambio extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Operación';

    protected static ?string $navigationLabel = 'Solicitudes';

    protected static ?string $title = 'Solicitudes de cambio';

    protected static ?string $slug = 'solicitudes-cambio';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.placeholder';
}
