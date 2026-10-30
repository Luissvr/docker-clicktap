<?php

namespace App\Support;

use App\Data\PaginaPublica;
use App\Models\Pagina;
use Illuminate\Support\Facades\Schema;

final class PublicPageExporter
{
    /** @return array{version: int, pages: list<array<string, mixed>>} */
    public function payload(?array $slugs = null): array
    {
        return [
            'version' => 1,
            'pages' => Pagina::query()
                ->useWritePdo()
                ->with(['enlaces' => fn ($query) => $query->useWritePdo()])
                ->where('activo', true)
                ->when(Schema::connection(CatalogConnection::enabled() ? 'supabase' : config('database.default'))->hasColumn('paginas', 'papelera_en'), fn ($query) => $query->whereNull('papelera_en'))
                ->when($slugs !== null, fn ($query) => $query->whereIn('slug', $slugs))
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
