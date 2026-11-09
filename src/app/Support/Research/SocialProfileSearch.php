<?php

namespace App\Support\Research;

use App\Exceptions\BusinessResearchException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Busca perfiles sociales de un negocio con la API oficial de Brave Search (operador `site:`).
 * Solo se acepta un resultado que sea realmente un perfil y cuyo texto mencione el nombre del negocio;
 * aun así el resultado es siempre una sugerencia que el administrador debe confirmar.
 */
class SocialProfileSearch
{
    private const NETWORKS = ['instagram' => 'instagram.com', 'facebook' => 'facebook.com', 'tiktok' => 'tiktok.com'];

    private const IGNORED_PATHS = [
        'instagram' => ['p', 'reel', 'reels', 'explore', 'stories', 'tv', 'accounts', 'directory', 'popular', 'about', 'legal', 'developer'],
        'facebook' => ['sharer', 'sharer.php', 'share', 'groups', 'events', 'watch', 'marketplace', 'posts', 'photo', 'photos', 'videos', 'reel', 'reels', 'hashtag', 'login', 'public', 'policies', 'help', 'story.php', 'permalink.php', 'profile.php'],
        'tiktok' => ['discover', 'tag', 'video', 'music', 'search', 'live', 'foryou', 'explore'],
    ];

    private const STOP_WORDS = ['la', 'el', 'los', 'las', 'de', 'del', 'y', 'e', 'en', 'the', 'and', 'spa', 'local'];

    public function __construct(private WebsitePageReader $reader) {}

    public function enabled(): bool
    {
        return filled(config('services.brave.key'));
    }

    /**
     * @param  list<string>  $networks
     * @return array<string, array{url: string, titulo: string}> red => perfil sugerido
     */
    public function find(string $businessName, string $city, array $networks): array
    {
        $networks = array_values(array_filter($networks, fn (string $network): bool => isset(self::NETWORKS[$network])));
        if (! $this->enabled() || $networks === []) {
            return [];
        }

        $found = [];
        $errors = [];
        foreach ($networks as $index => $network) {
            if ($index > 0) {
                usleep(max(0, (int) config('services.brave.delay_ms', 1100)) * 1000);
            }

            try {
                $profile = $this->searchNetwork($businessName, $city, $network);
            } catch (BusinessResearchException $exception) {
                $errors[] = $exception->getMessage();

                continue;
            }
            if ($profile !== null) {
                $found[$network] = $profile;
            }
        }

        if ($found === [] && $errors !== []) {
            throw new BusinessResearchException($errors[0]);
        }

        return $found;
    }

    /**
     * @return array{url: string, titulo: string}|null
     */
    private function searchNetwork(string $businessName, string $city, string $network): ?array
    {
        $query = 'site:'.self::NETWORKS[$network].' '.str_replace('"', '', $businessName).($city !== '' ? ' '.$city : '');

        try {
            $response = Http::withHeaders(['X-Subscription-Token' => (string) config('services.brave.key')])
                ->acceptJson()
                ->connectTimeout(8)
                ->timeout((int) config('services.brave.timeout', 15))
                ->get((string) config('services.brave.api_url'), [
                    'q' => $query,
                    'count' => 10,
                    'country' => 'CL',
                    'search_lang' => 'es',
                ]);
        } catch (Throwable $exception) {
            throw new BusinessResearchException('No se pudo consultar Brave Search: '.class_basename($exception).'.');
        }

        if (! $response->successful()) {
            throw new BusinessResearchException(match ($response->status()) {
                401, 403 => 'La clave de Brave Search no es válida o no tiene permiso.',
                402 => 'La cuenta de Brave Search no tiene crédito.',
                422 => 'Brave Search rechazó la consulta (HTTP 422).',
                429 => 'Brave Search limitó las consultas (cupo o velocidad); inténtalo más tarde.',
                default => 'Brave Search respondió HTTP '.$response->status().'.',
            });
        }

        foreach ((array) $response->json('web.results', []) as $result) {
            $url = is_array($result) ? ($result['url'] ?? null) : null;
            if (! is_string($url) || ! $this->isProfileUrl($url, $network)) {
                continue;
            }

            $title = trim(strip_tags((string) ($result['title'] ?? '')));
            $haystack = $title.' '.strip_tags((string) ($result['description'] ?? '')).' '.parse_url($url, PHP_URL_PATH);
            if (! $this->mentionsBusiness($businessName, $haystack)) {
                continue;
            }

            return ['url' => $this->reader->normalizeSocialUrl($url), 'titulo' => Str::limit($title, 120, '…')];
        }

        return null;
    }

    private function isProfileUrl(string $url, string $network): bool
    {
        if ($this->reader->socialNetwork($url) !== $network) {
            return false;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = $path === '' ? [] : explode('/', $path);
        if ($segments === [] || in_array(strtolower($segments[0]), self::IGNORED_PATHS[$network], true)) {
            return false;
        }

        return match ($network) {
            'instagram' => count($segments) === 1,
            'tiktok' => count($segments) === 1 && str_starts_with($segments[0], '@'),
            'facebook' => count($segments) === 1
                || (strtolower($segments[0]) === 'pages' && count($segments) >= 2)
                || (strtolower($segments[0]) === 'people' && count($segments) >= 3),
        };
    }

    /**
     * Valida un enlace o @usuario pegado por el administrador y lo devuelve como URL de perfil normalizada,
     * o null si no es el perfil de esa red (publicaciones, otras redes, texto suelto).
     */
    public function profileUrl(string $input, string $network): ?string
    {
        $input = trim($input);
        if (! isset(self::NETWORKS[$network]) || $input === '' || mb_strlen($input) > 300 || preg_match('/\s/', $input)) {
            return null;
        }

        $isLink = str_contains($input, '/') || preg_match('/(?:instagram|facebook|fb|tiktok)\.com/i', $input) === 1;
        if (! $isLink) {
            if (preg_match('/^@?([A-Za-z0-9._]{2,60})$/', $input, $match) !== 1) {
                return null;
            }
            $input = match ($network) {
                'instagram' => 'https://instagram.com/'.$match[1],
                'tiktok' => 'https://tiktok.com/@'.$match[1],
                'facebook' => 'https://facebook.com/'.$match[1],
            };
        } elseif (preg_match('#^https?://#i', $input) !== 1) {
            $input = 'https://'.$input;
        }

        if (filter_var($input, FILTER_VALIDATE_URL) === false || ! $this->isProfileUrl($input, $network)) {
            return null;
        }

        return $this->reader->normalizeSocialUrl($input);
    }

    private function mentionsBusiness(string $businessName, string $text): bool
    {
        $tokens = array_values(array_diff(explode(' ', $this->normalize($businessName)), self::STOP_WORDS, ['']));
        if ($tokens === []) {
            return false;
        }

        $normalizedText = $this->normalize($text);
        if (str_contains(str_replace(' ', '', $normalizedText), implode('', $tokens))) {
            return true;
        }

        $textTokens = explode(' ', $normalizedText);
        $matched = count(array_intersect($tokens, $textTokens));
        $required = count($tokens) <= 2 ? count($tokens) : (int) ceil(count($tokens) * 0.7);

        return $matched >= $required;
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($value))));
    }
}
