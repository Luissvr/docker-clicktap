<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ProspectMapResearch
{
    /** @return array<string, mixed> */
    public function parse(string $html, string $expectedName, string $expectedAddress): array
    {
        if (! preg_match('/initEmbed\((\[.*?\])\);/s', $html, $matches)) {
            return ['status' => 'unavailable', 'reason' => 'Google no devolvió una ficha legible.'];
        }

        $payload = json_decode($matches[1], true);
        $candidates = [];
        $this->collectPlaces(is_array($payload) ? $payload : [], $candidates);

        foreach ($candidates as &$candidate) {
            $candidate['name_matches'] = $this->nameMatches($expectedName, $candidate['name']);
            $candidate['address_matches'] = $this->addressMatches($expectedAddress, $candidate['address']);
            $candidate['is_business'] = $candidate['category'] !== null;
        }
        unset($candidate);

        $matched = array_values(array_filter($candidates, fn (array $candidate): bool => $candidate['name_matches']
            && $candidate['address_matches'] && $candidate['is_business']));

        if (count($matched) !== 1) {
            return [
                'status' => $candidates === [] ? 'not_found' : 'needs_review',
                'reason' => 'Se requiere una única empresa que coincida en nombre, calle, número y ciudad.',
                'candidates' => $candidates,
            ];
        }

        return ['status' => 'matched', 'place' => $matched[0]];
    }

    /** @param array<mixed> $node
     * @param  list<array<string, mixed>>  $places
     */
    private function collectPlaces(array $node, array &$places): void
    {
        $ids = array_values(array_filter($node, fn (mixed $value): bool => is_string($value)
            && preg_match('/^ChI[A-Za-z0-9_-]{15,}$/', $value) === 1));

        if (count($ids) === 1 && is_string($node[1] ?? null) && is_string($node[13] ?? null)) {
            $strings = [];
            array_walk_recursive($node, static function (mixed $value) use (&$strings): void {
                if (is_string($value)) {
                    $strings[] = $value;
                }
            });
            $phones = array_values(array_filter($strings, fn (string $value): bool => str_starts_with($value, 'tel:')));
            $phone = isset($phones[0]) ? preg_replace('/[^\d+]/', '', substr($phones[0], 4)) : null;
            $website = $this->cleanWebsite($node[11][0] ?? null);
            $hours = [];
            $this->collectHours($node, $hours);
            $places[] = [
                'place_id' => $ids[0],
                'name' => $node[1],
                'address' => $node[13],
                'category' => is_string($node[12] ?? null) ? $node[12] : null,
                'phone' => $phone,
                'website' => $website,
                'website_type' => $website ? $this->websiteType($website) : null,
                'rating' => is_numeric($node[3] ?? null) ? round((float) $node[3], 1) : null,
                'review_count_label' => is_string($node[4] ?? null) ? $node[4] : null,
                'opening_hours' => $hours,
                'coordinates' => $node[0][2] ?? null,
            ];

            return;
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $this->collectPlaces($child, $places);
            }
        }
    }

    private function nameMatches(string $expected, string $actual): bool
    {
        $stopWords = ['la', 'el', 'los', 'las', 'de', 'del', 'desde', 'don', 'y', 'car', 'servicio',
            'service', 'automotriz', 'mecanica', 'mecanico', 'integral', 'concepcion', 'distribuidor', 'spa'];
        $tokens = array_values(array_diff(explode(' ', $this->normalize($expected)), $stopWords, ['']));
        $actualTokens = explode(' ', $this->normalize($actual));

        return $tokens !== [] && array_diff($tokens, $actualTokens) === [];
    }

    private function addressMatches(string $expected, string $actual): bool
    {
        $street = $this->normalize(explode(',', $expected)[0]);
        $street = preg_replace('/^(avenida|av|calle) /', '', $street);
        $actualTokens = explode(' ', $this->normalize($actual));
        $expectedTokens = explode(' ', $street);

        return array_diff($expectedTokens, $actualTokens) === []
            && in_array('concepcion', $actualTokens, true)
            && preg_match('/\d/', $street) === 1;
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($value))));
    }

    private function cleanWebsite(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }
        if (str_starts_with($url, '/url?')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $parameters);
            $url = $parameters['q'] ?? $parameters['url'] ?? '';
        }

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : null;
    }

    private function websiteType(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (['instagram', 'facebook', 'tiktok'] as $type) {
            if ($host === $type.'.com' || str_ends_with($host, '.'.$type.'.com')) {
                return $type;
            }
        }

        return 'web';
    }

    /** @param array<mixed> $node
     * @param  array<string, string>  $hours
     */
    private function collectHours(array $node, array &$hours): void
    {
        $days = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
        if (is_string($node[0] ?? null) && in_array($node[0], $days, true) && is_array($node[3] ?? null)) {
            $periods = array_filter(array_map(fn (mixed $period): ?string => is_array($period)
                && is_string($period[0] ?? null) ? $period[0] : null, $node[3]));
            if ($periods !== []) {
                $hours[$node[0]] = implode(' / ', $periods);
            }

            return;
        }
        foreach ($node as $child) {
            if (is_array($child)) {
                $this->collectHours($child, $hours);
            }
        }
    }
}
