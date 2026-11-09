<?php

namespace App\Support\Research;

use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Throwable;

/**
 * Extrae de forma determinista datos de contacto y redes de una página web.
 */
class WebsitePageReader
{
    public const MAX_TEXT_LENGTH = 12000;

    private const SOCIAL_HOSTS = [
        'instagram' => ['instagram.com'],
        'facebook' => ['facebook.com', 'fb.com'],
        'tiktok' => ['tiktok.com'],
    ];

    /**
     * @return array{
     *     url: string,
     *     title: ?string,
     *     description: ?string,
     *     image: ?string,
     *     logo: ?string,
     *     text: string,
     *     links: list<string>,
     *     social: array<string, string>,
     *     whatsapp: list<string>,
     *     phones: list<string>,
     *     json_ld: list<array<string, mixed>>
     * }
     */
    public function read(string $html, string $url): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        $jsonLd = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $decoded = json_decode(trim($script->textContent), true);
            if (is_array($decoded)) {
                foreach ($this->flattenJsonLd($decoded) as $node) {
                    $jsonLd[] = $node;
                }
            }
        }

        foreach ($xpath->query('//script|//style|//noscript|//svg|//template') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $links = [];
        foreach ($xpath->query('//a[@href]') as $anchor) {
            $absolute = $this->absoluteUrl($url, trim($anchor instanceof DOMElement ? $anchor->getAttribute('href') : ''));
            if ($absolute !== null) {
                $links[] = $absolute;
            }
        }
        foreach ($jsonLd as $node) {
            foreach ((array) ($node['sameAs'] ?? []) as $sameAs) {
                if (is_string($sameAs) && ($absolute = $this->absoluteUrl($url, $sameAs)) !== null) {
                    $links[] = $absolute;
                }
            }
        }
        $links = array_slice(array_values(array_unique($links)), 0, 300);

        $phones = [];
        $whatsapp = [];
        $social = [];
        foreach ($links as $link) {
            if (str_starts_with($link, 'tel:')) {
                $phones[] = $this->digits($link);

                continue;
            }
            $number = $this->whatsappNumber($link);
            if ($number !== null) {
                $whatsapp[] = $number;

                continue;
            }
            $network = $this->socialNetwork($link);
            if ($network !== null && ! isset($social[$network])) {
                $social[$network] = $this->normalizeSocialUrl($link);
            }
        }
        foreach ($jsonLd as $node) {
            if (is_string($node['telephone'] ?? null)) {
                $phones[] = $this->digits($node['telephone']);
            }
        }

        $body = $xpath->query('//body')->item(0);
        $text = trim((string) preg_replace('/\s+/u', ' ', $body?->textContent ?? $document->textContent));

        return [
            'url' => $url,
            'title' => $this->meta($xpath, 'og:title') ?? $this->nullableText($xpath->query('//title')->item(0)?->textContent),
            'description' => $this->meta($xpath, 'og:description') ?? $this->meta($xpath, 'description'),
            'image' => ($image = $this->meta($xpath, 'og:image')) !== null ? $this->absoluteUrl($url, $image) : null,
            'logo' => $this->logo($xpath, $url, $jsonLd),
            'text' => mb_substr($text, 0, self::MAX_TEXT_LENGTH),
            'links' => $links,
            'social' => $social,
            'whatsapp' => array_values(array_unique(array_filter($whatsapp))),
            'phones' => array_values(array_unique(array_filter($phones))),
            'json_ld' => $jsonLd,
        ];
    }

    public function socialNetwork(string $url): ?string
    {
        $host = $this->host($url);
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($host === null || $path === '' || preg_match('#(^|/)(sharer|share|intent|dialog|plugins)(\.php)?(/|$)#i', $path)) {
            return null;
        }

        foreach (self::SOCIAL_HOSTS as $network => $hosts) {
            foreach ($hosts as $socialHost) {
                if ($host === $socialHost || str_ends_with($host, '.'.$socialHost)) {
                    return $network;
                }
            }
        }

        return null;
    }

    public function normalizeSocialUrl(string $url): string
    {
        $host = (string) $this->host($url);
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');

        return 'https://'.preg_replace('/^(www\.|m\.|web\.)/', '', $host).$path;
    }

    public function whatsappNumber(string $url): ?string
    {
        $host = $this->host($url);
        if ($host === 'wa.me') {
            $digits = $this->digits((string) parse_url($url, PHP_URL_PATH));

            return $digits !== '' ? $digits : null;
        }
        if (in_array($host, ['api.whatsapp.com', 'whatsapp.com', 'web.whatsapp.com'], true)) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $digits = $this->digits((string) ($query['phone'] ?? ''));

            return $digits !== '' ? $digits : null;
        }

        return null;
    }

    public function digits(string $value): string
    {
        return (string) preg_replace('/\D/', '', $value);
    }

    /**
     * @param  array<mixed>  $node
     * @return list<array<string, mixed>>
     */
    private function flattenJsonLd(array $node): array
    {
        if (array_is_list($node)) {
            $nodes = [];
            foreach ($node as $item) {
                if (is_array($item)) {
                    array_push($nodes, ...$this->flattenJsonLd($item));
                }
            }

            return $nodes;
        }
        if (isset($node['@graph']) && is_array($node['@graph'])) {
            return $this->flattenJsonLd($node['@graph']);
        }

        return [$node];
    }

    /**
     * Mejor candidato a logo: el declarado en los datos estructurados, luego el ícono táctil y por último la imagen social.
     *
     * @param  list<array<string, mixed>>  $jsonLd
     */
    private function logo(DOMXPath $xpath, string $url, array $jsonLd): ?string
    {
        foreach ($jsonLd as $node) {
            $logo = $node['logo'] ?? null;
            $logo = is_array($logo) ? ($logo['url'] ?? $logo['contentUrl'] ?? ($logo[0] ?? null)) : $logo;
            if (is_string($logo) && ($absolute = $this->absoluteUrl($url, trim($logo))) !== null) {
                return $absolute;
            }
        }

        $best = null;
        $bestSize = -1;
        foreach ($xpath->query('//link[@href][contains(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "apple-touch-icon")]') as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }
            $size = (int) $link->getAttribute('sizes');
            if ($size > $bestSize) {
                $bestSize = $size;
                $best = $link->getAttribute('href');
            }
        }
        if ($best !== null && ($absolute = $this->absoluteUrl($url, trim($best))) !== null) {
            return $absolute;
        }

        $image = $this->meta($xpath, 'og:image');

        return $image !== null ? $this->absoluteUrl($url, $image) : null;
    }

    private function meta(DOMXPath $xpath, string $name): ?string
    {
        $node = $xpath->query('//meta[@property="'.$name.'" or @name="'.$name.'"]')->item(0);

        return $node instanceof DOMElement ? $this->nullableText($node->getAttribute('content')) : null;
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : mb_substr($value, 0, 500);
    }

    private function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    private function absoluteUrl(string $base, string $href): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || preg_match('/^(javascript|mailto|data):/i', $href)) {
            return null;
        }
        if (str_starts_with(strtolower($href), 'tel:')) {
            return 'tel:'.$this->digits($href);
        }

        try {
            $absolute = (string) UriResolver::resolve(new Uri($base), new Uri($href));
        } catch (Throwable) {
            return null;
        }

        return preg_match('#^https?://#i', $absolute) ? $absolute : null;
    }
}
