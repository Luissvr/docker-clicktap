<?php

namespace App\Support;

use App\Exceptions\InvalidPageRouteException;
use App\Models\Pagina;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PageRoute
{
    /** @var list<string> */
    private const RESERVED = [
        '404.html',
        '_astro',
        'admin',
        'api',
        'build',
        'favicon.ico',
        'livewire',
        'robots.txt',
        'storage',
    ];

    private function __construct() {}

    public static function clean(string $route): string
    {
        $route = trim($route, " \n\r\t\v\0/");
        $normalized = Str::lower($route);

        if (
            $route === ''
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]*\z/D', $route) !== 1
            || in_array($normalized, self::RESERVED, true)
        ) {
            throw new InvalidPageRouteException;
        }

        return $route;
    }

    public static function assertAvailable(string $route, ?string $pageId = null): void
    {
        $normalized = Str::lower(self::clean($route));

        $slugTaken = Pagina::query()
            ->when($pageId, fn ($query) => $query->where('id', '!=', $pageId))
            ->whereRaw('LOWER(slug) = ?', [$normalized])
            ->exists();

        $aliasTaken = Schema::hasTable('alias_pagina') && DB::table('alias_pagina')
            ->when($pageId, fn ($query) => $query->where('pagina_id', '!=', $pageId))
            ->whereRaw('LOWER(ruta) = ?', [$normalized])
            ->exists();

        if ($slugTaken || $aliasTaken) {
            throw new InvalidPageRouteException('La ruta ya está reservada por otra página.');
        }
    }
}
