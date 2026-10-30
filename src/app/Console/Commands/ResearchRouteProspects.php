<?php

namespace App\Console\Commands;

use App\Support\ProspectMapResearch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ResearchRouteProspects extends Command
{
    protected $signature = 'clicktap:research-route
        {--file=database/route_prospects_2026_10_28.json : Manifiesto JSON relativo al proyecto}
        {--from=1 : Primera visita}
        {--limit=5 : Cantidad de visitas}
        {--refresh : Consultar nuevamente fichas ya recopiladas}';

    protected $description = 'Recopila datos públicos de Google Maps, verifica la identidad y conserva fuentes y pendientes';

    public function handle(ProspectMapResearch $parser): int
    {
        $path = base_path($this->option('file'));
        $prospects = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        Validator::make(['prospects' => $prospects, 'from' => $this->option('from'), 'limit' => $this->option('limit')], [
            'prospects' => ['required', 'array'],
            'prospects.*.order' => ['required', 'integer', 'min:1', 'distinct'],
            'prospects.*.name' => ['required', 'string'],
            'prospects.*.address' => ['required', 'string'],
            'from' => ['required', 'integer', 'min:1'],
            'limit' => ['required', 'integer', 'min:1', 'max:100'],
        ])->validate();
        $selected = collect($prospects)->filter(fn (array $p): bool => $p['order'] >= (int) $this->option('from'))
            ->sortBy('order')->take((int) $this->option('limit'));
        $failures = 0;

        foreach ($selected as $index => $prospect) {
            $query = $prospect['search_query'] ?? $prospect['name'].' '.$prospect['address'].' Chile';
            $inputFingerprint = hash('sha256', json_encode([
                'name' => $prospect['name'],
                'address' => $prospect['address'],
                'query' => $query,
            ], JSON_THROW_ON_ERROR));
            if (! $this->option('refresh') && isset($prospect['research']['fetched_at'])
                && ($prospect['research']['input_fingerprint'] ?? null) === $inputFingerprint
                && in_array($prospect['research']['status'], ['matched', 'needs_review'], true)) {
                $this->line($prospect['order'].'. '.$prospect['name'].' — datos guardados');

                continue;
            }

            $source = 'https://maps.google.com/maps?'.http_build_query(['q' => $query, 'output' => 'embed', 'hl' => 'es']);
            try {
                $response = Http::connectTimeout(10)->timeout(25)->get($source);
                $research = $response->successful()
                    ? $parser->parse($response->body(), $prospect['name'], $prospect['address'])
                    : ['status' => 'unavailable', 'reason' => 'HTTP '.$response->status()];
            } catch (Throwable $exception) {
                $research = ['status' => 'unavailable', 'reason' => class_basename($exception)];
            }

            $research['input_fingerprint'] = $inputFingerprint;
            $research['fetched_at'] = now()->toIso8601String();
            $research['source_url'] = $source;
            $research['google_search_url'] = 'https://www.google.com/search?'.http_build_query(['q' => $query]);
            $research['social_search_url'] = 'https://www.google.com/search?'.http_build_query([
                'q' => $prospect['name'].' Concepción (site:instagram.com OR site:facebook.com OR site:tiktok.com)',
            ]);
            $research['pending'] = ['Confirmar logo y datos con el encargado antes de publicar.'];
            if ($research['status'] !== 'matched') {
                $research['pending'][] = 'Confirmar la ficha comercial de Google; no utilizar el Place ID candidato.';
            }
            if (empty($research['place']['website'])) {
                $research['pending'][] = 'Buscar o confirmar redes y sitio web; Google no aportó un enlace.';
            }
            $prospects[$index]['research'] = $research;
            File::put($path, json_encode($prospects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL, true);
            $this->line($prospect['order'].'. '.$prospect['name'].' — '.$research['status']
                .' — '.($research['place']['place_id'] ?? 'Place ID pendiente'));
            if ($research['status'] === 'unavailable') {
                $failures++;
            }
        }
        $this->info('Fichas y fuentes guardadas en '.$path);

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
