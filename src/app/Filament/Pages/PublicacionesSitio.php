<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PublicacionesSitio extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-up';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Publicaciones';

    protected static ?string $title = 'Publicaciones';

    protected static ?string $slug = 'publicaciones-sitio';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.placeholder';
}
