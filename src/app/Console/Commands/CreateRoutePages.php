<?php

namespace App\Console\Commands;

use App\Actions\CreatePageAction;
use App\Models\Pagina;
use App\Models\User;
use App\Support\CatalogConnection;
use App\Support\LinkTypeHelper;
use App\Support\PagePreviewCompiler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateRoutePages extends Command
{
    protected $signature = 'clicktap:create-route-pages
        {--file=database/route_prospects_2026_10_28.json : Manifiesto investigado}
        {--actor= : ID o correo del usuario del panel}
        {--from=1 : Primera visita}
        {--limit=5 : Cantidad de visitas}
        {--dry-run : Mostrar las páginas sin guardarlas}
        {--previews : Exportar vistas HTML locales para revisión}';

    protected $description = 'Crea borradores por lotes con el diseño de páginas existentes y datos trazables';

    public function handle(CreatePageAction $createPage): int
    {
        $path = base_path($this->option('file'));
        $prospects = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        Validator::make(['prospects' => $prospects, 'from' => $this->option('from'), 'limit' => $this->option('limit')], [
            'prospects' => ['required', 'array'],
            'prospects.*.order' => ['required', 'integer', 'min:1', 'distinct'],
            'prospects.*.slug' => ['required', 'string', 'distinct'],
            'prospects.*.name' => ['required', 'string'],
            'prospects.*.address' => ['required', 'string'],
            'prospects.*.category' => ['required', 'string'],
            'prospects.*.template' => ['required', 'string'],
            'from' => ['required', 'integer', 'min:1'],
            'limit' => ['required', 'integer', 'min:1', 'max:100'],
        ])->validate();
        $actorReference = (string) $this->option('actor');
        $actor = User::query()->where('activo', true)
            ->where(Str::isUuid($actorReference) ? 'id' : 'email', $actorReference)->first();
        if (! $actor) {
            $this->error('Indica --actor con el ID o correo de un usuario activo del panel.');

            return self::FAILURE;
        }
        $selected = collect($prospects)->filter(fn (array $p): bool => $p['order'] >= (int) $this->option('from'))
            ->sortBy('order')->take((int) $this->option('limit'));
        Validator::make(['selected' => $selected->values()->all()], [
            'selected.*.research.status' => ['required', 'in:matched,needs_review,not_found,unavailable'],
            'selected.*.research.place.place_id' => ['required_if:selected.*.research.status,matched'],
        ])->validate();

        foreach ($selected as $prospect) {
            if (Pagina::query()->where('slug', $prospect['slug'])->exists()) {
                $this->line($prospect['order'].'. '.$prospect['name'].' — ya existe; se conserva.');

                continue;
            }
            $template = Pagina::query()->where('slug', $prospect['template'])->firstOrFail();
            $research = $prospect['research'];
            $place = $research['status'] === 'matched' ? $research['place'] : [];
            $links = $this->links($prospect, $place);
            $pageData = array_merge($template->only([
                'tema', 'tipografia', 'color_fondo', 'color_texto', 'color_boton',
                'color_texto_boton', 'logo_layout', 'logo_width', 'paleta_personalizada',
            ]), [
                'nombre_publico' => $prospect['name'],
                'direccion_publica' => $prospect['address'],
                'telefono_publico' => $place['phone'] ?? $prospect['phone'] ?? null,
                'activo' => false,
            ]);

            if ($this->option('dry-run')) {
                $this->line($prospect['order'].'. '.$prospect['name'].' — borrador /'.$prospect['slug']
                    .' — plantilla '.$prospect['template'].' — '.count($links).' enlaces');

                continue;
            }
            $page = CatalogConnection::transaction(function () use ($actor, $prospect, $research, $place, $links, $pageData, $createPage): Pagina {
                $notes = 'Prospecto de la ruta, visita '.$prospect['order'].'. Datos públicos recopilados en '
                    .($research['fetched_at'] ?? 'fecha pendiente').'.'.PHP_EOL
                    .'Verificación Google: '.$research['status'].PHP_EOL
                    .implode(PHP_EOL, $prospect['verification_notes'] ?? []).PHP_EOL
                    .implode(PHP_EOL, $research['pending'] ?? []).PHP_EOL
                    .'Fuentes:'.PHP_EOL.implode(PHP_EOL, array_filter([
                        ...($prospect['sources'] ?? []), $research['source_url'] ?? null,
                        $research['google_search_url'] ?? null, $research['social_search_url'] ?? null,
                    ]));
                $page = $createPage->handle($actor, [], [
                    'nombre_interno' => $prospect['name'],
                    'direccion_operativa' => $prospect['address'],
                    'notas_internas' => $notes,
                ], $prospect['slug'], $pageData, $links);
                $page->update(['contenido_extra' => [
                    'tagline' => $prospect['category'],
                    'transfer_logo' => 'none',
                    'prospect_route' => [
                        'manifest' => basename($this->option('file')),
                        'order' => $prospect['order'],
                        'template' => $prospect['template'],
                        'research_status' => $research['status'],
                        'place_id' => $place['place_id'] ?? null,
                        'researched_at' => $research['fetched_at'] ?? null,
                        'verification_notes' => $prospect['verification_notes'] ?? [],
                    ],
                ]]);

                return $page;
            });
            $this->info($prospect['order'].'. '.$page->nombre_publico.' — borrador creado — '.route('paginas.preview', $page->slug));
        }

        if ($this->option('previews') && ! $this->option('dry-run')) {
            $this->exportPreviews($prospects);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $prospect
     * @param  array<string, mixed>  $place
     * @return list<array<string, mixed>>
     */
    private function links(array $prospect, array $place): array
    {
        $links = [];
        if (! empty($prospect['whatsapp'])) {
            $links[] = ['tipo' => 'whatsapp', 'etiqueta' => 'Escríbenos por WhatsApp',
                'url' => 'https://wa.me/'.preg_replace('/\D/', '', $prospect['whatsapp'])];
        }
        if (! empty($place['place_id'])) {
            $links[] = ['tipo' => 'review', 'etiqueta' => 'Déjanos una reseña',
                'url' => LinkTypeHelper::reviewUrlFromPlaceId($place['place_id'])];
        }
        if (! empty($place['website'])) {
            $type = $place['website_type'] ?? 'web';
            $links[] = ['tipo' => $type, 'etiqueta' => $type === 'web' ? 'Visita nuestra web' : LinkTypeHelper::label($type),
                'url' => $place['website']];
        }
        $maps = ['api' => 1, 'query' => $prospect['name'].' '.$prospect['address']];
        if (! empty($place['place_id'])) {
            $maps['query_place_id'] = $place['place_id'];
        }
        $links[] = ['tipo' => 'location', 'etiqueta' => 'Cómo llegar',
            'url' => 'https://www.google.com/maps/search/?'.http_build_query($maps)];

        return $links;
    }

    /** @param list<array<string, mixed>> $prospects */
    private function exportPreviews(array $prospects): void
    {
        $directory = storage_path('app/route-previews');
        File::ensureDirectoryExists($directory);
        $pages = Pagina::query()->whereIn('slug', array_column($prospects, 'slug'))->with('enlaces')->get()->keyBy('slug');
        $items = [];
        foreach ($prospects as $prospect) {
            $page = $pages->get($prospect['slug']);
            if (! $page || data_get($page->contenido_extra, 'prospect_route.manifest') !== basename($this->option('file'))) {
                continue;
            }
            File::put($directory.'/'.$page->slug.'.html', PagePreviewCompiler::compile([], $page, standalone: true));
            $items[] = [
                'order' => $prospect['order'], 'name' => $page->nombre_publico, 'address' => $page->direccion_publica,
                'file' => $page->slug.'.html', 'panel_url' => route('paginas.preview', $page->slug),
                'review_ready' => $page->enlaces->contains('tipo', 'review'),
                'template' => $prospect['template'],
                'verification_notes' => $prospect['verification_notes'] ?? [],
            ];
        }
        File::put($directory.'/index.html', view('paginas.route-preview-index', ['items' => $items])->render());
        $this->info('Vistas HTML: '.$directory.'/index.html');
    }
}
