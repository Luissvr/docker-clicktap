<?php

namespace App\Support\Research;

use App\Exceptions\BusinessResearchException;
use App\Support\ProspectMapResearch;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Busca un nombre en Google Maps y devuelve todos los negocios que coinciden.
 */
class GoogleMapsCandidateSearch
{
    public const MAX_CANDIDATES = 8;

    public function __construct(private ProspectMapResearch $parser) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $name, string $city): array
    {
        $candidates = $this->query(trim($name.' '.$city));
        if ($candidates === [] && trim($city) !== '') {
            $candidates = $this->query(trim($name));
        }

        return array_map(function (array $candidate): array {
            $candidate['maps_url'] = 'https://www.google.com/maps/place/?q=place_id:'.rawurlencode($candidate['place_id']);

            return $candidate;
        }, array_slice($candidates, 0, self::MAX_CANDIDATES));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function query(string $text): array
    {
        try {
            $response = Http::connectTimeout(10)->timeout(25)->get('https://maps.google.com/maps?'.http_build_query([
                'q' => $text.' Chile',
                'output' => 'embed',
                'hl' => 'es',
            ]));
        } catch (Throwable $exception) {
            throw new BusinessResearchException('No se pudo consultar Google Maps: '.class_basename($exception).'.');
        }

        if (! $response->successful()) {
            throw new BusinessResearchException('Google Maps respondió HTTP '.$response->status().'.');
        }

        return $this->parser->candidates($response->body());
    }
}
