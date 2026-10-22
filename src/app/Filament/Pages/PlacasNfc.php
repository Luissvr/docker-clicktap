<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PlacasNfc extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Operación';

    protected static ?string $navigationLabel = 'Placas NFC';

    protected static ?string $title = 'Placas NFC';

    protected static ?string $slug = 'placas-nfc';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.placeholder';
}
