<?php

namespace App\Support;

use App\Data\PaginaPublica;
use App\Models\Pagina;

final class PublicPageExporter
{
    /** @return array{version: int, pages: list<array<string, mixed>>} */
    public function payload(): array
    {
        return [
            'version' => 1,
            'pages' => Pagina::query()
                ->with('enlaces')
                ->where('activo', true)
                ->orderBy('slug')
                ->get()
                ->map(fn (Pagina $pagina): array => PaginaPublica::fromModel($pagina)->toArray())
                ->values()
                ->all(),
        ];
    }
}
