<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Cobros extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Finanzas';

    protected static ?string $navigationLabel = 'Cobros';

    protected static ?string $title = 'Cobros';

    protected static ?string $slug = 'cobros';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.placeholder';
}
