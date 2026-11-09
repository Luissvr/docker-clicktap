<?php

namespace App\Support\Research;

use App\Exceptions\BusinessResearchException;
use Closure;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Descarga páginas públicas sin permitir acceso a la red interna (protección SSRF).
 */
class SafeHttpFetcher
{
    public const MAX_BYTES = 2_097_152;

    private const MAX_REDIRECTS = 3;

    private const ALLOWED_PORTS = ['http' => 80, 'https' => 443];

    /**
     * @param  (Closure(string): list<string>)|null  $resolver  Devuelve las IP de un host.
     */
    public function __construct(private ?Closure $resolver = null) {}

    /**
     * @return array{url: string, status: int, content_type: string, body: string}
     */
    public function get(string $url, string $accept = 'text/html,application/xhtml+xml'): array
    {
        $current = trim($url);

        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            ['host' => $host, 'port' => $port, 'ip' => $ip] = $this->assertPublicUrl($current);
            $pinnedAddress = str_contains($ip, ':') ? '['.$ip.']' : $ip;

            try {
                $response = Http::withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$pinnedAddress]],
                    'on_headers' => function (ResponseInterface $response): void {
                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                            throw new BusinessResearchException('La página supera el tamaño permitido.');
                        }
                    },
                    'progress' => function (int $downloadTotal, int $downloaded): void {
                        if ($downloaded > self::MAX_BYTES) {
                            throw new BusinessResearchException('La página supera el tamaño permitido.');
                        }
                    },
                ])
                    ->withUserAgent('ClickTapResearch/1.0')
                    ->accept($accept)
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->get($current);
            } catch (Throwable $exception) {
                throw new BusinessResearchException('No se pudo descargar la página: '.class_basename($exception).'.');
            }

            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                $location = $response->header('Location');
                if ($location === '') {
                    throw new BusinessResearchException('La página redirige sin indicar destino.');
                }
                $current = (string) UriResolver::resolve(new Uri($current), new Uri($location));

                continue;
            }

            $body = $response->body();
            if (strlen($body) > self::MAX_BYTES) {
                throw new BusinessResearchException('La página supera el tamaño permitido.');
            }

            return [
                'url' => $current,
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'body' => $body,
            ];
        }

        throw new BusinessResearchException('La página redirige demasiadas veces.');
    }

    /**
     * @return array{host: string, port: int, ip: string}
     */
    public function assertPublicUrl(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! isset(self::ALLOWED_PORTS[$scheme]) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new BusinessResearchException('Solo se permiten direcciones http o https públicas.');
        }

        $port = (int) ($parts['port'] ?? self::ALLOWED_PORTS[$scheme]);
        if ($port !== self::ALLOWED_PORTS[$scheme]) {
            throw new BusinessResearchException('Solo se permiten los puertos web estándar.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($addresses === []) {
            throw new BusinessResearchException('No se encontró el dominio '.$host.'.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new BusinessResearchException('La dirección apunta a una red interna y fue bloqueada.');
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $addresses[0]];
    }

    public function isPublicAddress(string $address): bool
    {
        if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($address);

            return ! ($long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255'));
        }

        return ! str_starts_with(strtolower($address), 'fc') && ! str_starts_with(strtolower($address), 'fd');
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        if ($this->resolver !== null) {
            return array_values(($this->resolver)($host));
        }

        $addresses = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
