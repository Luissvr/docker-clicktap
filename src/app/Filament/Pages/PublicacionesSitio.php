<?php

namespace App\Filament\Pages;

use App\Support\PublicationQueue;
use Filament\Pages\Page;

class PublicacionesSitio extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-up';

    protected static ?string $navigationLabel = 'Publicaciones';

    protected static ?string $title = 'Publicaciones';

    protected static ?string $slug = 'publicaciones-sitio';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.publicaciones-sitio';

    public function getViewData(): array
    {
        return app(PublicationQueue::class)->status();
    }
}
