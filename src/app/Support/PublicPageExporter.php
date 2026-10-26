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
                ->useWritePdo()
                ->with(['enlaces' => fn ($query) => $query->useWritePdo()])
                ->where('activo', true)
                ->orderBy('slug')
                ->get()
                ->map(fn (Pagina $pagina): array => PaginaPublica::fromModel($pagina)->toArray())
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function page(Pagina $pagina): array
    {
        $pagina->load(['enlaces' => fn ($query) => $query->useWritePdo()]);

        return PaginaPublica::fromModel($pagina)->toArray();
    }
}
