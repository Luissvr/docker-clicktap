<?php

namespace App\Support;

use App\Models\Pagina;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class LogoCache
{
    private const MAX_BYTES = 20 * 1024 * 1024;

    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function url(Pagina $page): ?string
    {
        if (! $this->cacheKey($page)) {
            return null;
        }

        return route('paginas.cached-logo', [
            'pagina' => $page,
            'version' => $this->version($page),
        ]);
    }

    public function version(Pagina $page): string
    {
        return substr(hash('sha256', $page->logo_disk.'|'.$page->logo_path), 0, 16);
    }

    public function isCached(Pagina $page): bool
    {
        $key = $this->cacheKey($page);

        return $key !== null && Storage::disk('local')->exists($key);
    }

    public function isKnownMissing(Pagina $page): bool
    {
        $key = $this->cacheKey($page);

        return $key !== null && ! $this->isCached($page)
            && app(CatalogSyncState::class)->cache()->has($this->missingKey($key));
    }

    public function isRecovered(Pagina $page): bool
    {
        $key = $this->cacheKey($page);

        return $key !== null && $this->isCached($page)
            && Storage::disk('local')->get($key.'.source') === 'published';
    }

    public function path(Pagina $page, bool $retryMissing = false): ?string
    {
        $key = $this->cacheKey($page);
        if ($key === null) {
            return null;
        }

        $local = Storage::disk('local');
        if ($local->exists($key)) {
            return $local->path($key);
        }

        $cache = app(CatalogSyncState::class)->cache();
        $missingKey = $this->missingKey($key);
        if ($retryMissing) {
            $cache->forget($missingKey);
        } elseif ($cache->has($missingKey)) {
            return null;
        }

        return $cache->lock('logo-download:'.hash('sha256', $key), 120)->block(10, function () use ($page, $key, $local, $cache, $missingKey): ?string {
            if ($local->exists($key)) {
                return $local->path($key);
            }
            $source = 'supabase';
            if (Storage::disk('supabase')->exists($page->logo_path)) {
                $stream = Storage::disk('supabase')->readStream($page->logo_path);
            } else {
                $contents = $this->publishedCopy($page);
                if ($contents === null) {
                    $cache->put($missingKey, true, now()->addHours(6));

                    return null;
                }
                $stream = fopen('php://temp', 'w+b');
                fwrite($stream, $contents);
                rewind($stream);
                $source = 'published';
            }
            if (! is_resource($stream)) {
                throw new RuntimeException('No se pudo leer la imagen de origen.');
            }
            $temporary = $key.'.part-'.Str::uuid();
            try {
                if (! $local->put($temporary, $stream)) {
                    throw new RuntimeException('No se pudo guardar la imagen en la caché local.');
                }
            } finally {
                fclose($stream);
            }

            try {
                $absolute = $local->path($temporary);
                if (filesize($absolute) > self::MAX_BYTES || ! in_array(mime_content_type($absolute), self::IMAGE_TYPES, true)) {
                    throw new RuntimeException('Supabase entregó un archivo que no es una imagen admitida.');
                }
                if (! $local->move($temporary, $key)) {
                    throw new RuntimeException('No se pudo completar la caché de la imagen.');
                }
                if ($source === 'published') {
                    $local->put($key.'.source', $source);
                }

                return $local->path($key);
            } catch (Throwable $exception) {
                $local->delete($temporary);
                throw $exception;
            }
        });
    }

    /** @return array{cached: int, downloaded: int, recovered: int, missing: list<string>, errors: list<string>} */
    public function warm(bool $retryMissing = false): array
    {
        $summary = ['cached' => 0, 'downloaded' => 0, 'recovered' => 0, 'missing' => [], 'errors' => []];
        $pages = Pagina::query()->where('logo_disk', 'supabase')->whereNotNull('logo_path')->get();
        foreach ($pages as $page) {
            $cached = $this->isCached($page);
            try {
                if ($this->path($page, $retryMissing) === null) {
                    $summary['missing'][] = $page->slug;
                } else {
                    $summary[$cached ? 'cached' : ($this->isRecovered($page) ? 'recovered' : 'downloaded')]++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $summary['errors'][] = $page->slug;
            }
        }

        return $summary;
    }

    private function publishedCopy(Pagina $page): ?string
    {
        $repository = trim((string) config('services.github.repository'));
        $token = trim((string) config('services.github.token'));
        $commit = (string) $page->publicacion_commit;
        $hasCommit = preg_match('/\A[a-f0-9]{40}\z/i', $commit) === 1;
        if ($repository === '' || $token === '' || (! $hasCommit && $page->publicacion_estado !== 'legado')) {
            return null;
        }

        $extension = strtolower(pathinfo($page->logo_path, PATHINFO_EXTENSION));
        $path = 'public/generated/logos/'.rawurlencode($page->slug).'.'.$extension;
        $base = rtrim((string) config('services.github.api_url', 'https://api.github.com'), '/').'/repos/'.$repository;
        $client = Http::withToken($token)->acceptJson()->timeout(20)->withHeaders(['User-Agent' => 'ClickTap']);
        if ($hasCommit) {
            $response = $client->get($base.'/contents/'.$path, ['ref' => $commit]);
        }
        if (! $hasCommit || $response->status() === 404) {
            $response = $client->get($base.'/contents/'.$path, ['ref' => config('services.github.branch', 'main')]);
        }
        if ($response->status() === 404) {
            return null;
        }
        $response->throw();
        if ((int) $response->json('size', 0) > self::MAX_BYTES) {
            throw new RuntimeException('La copia publicada supera el tamaño permitido.');
        }
        $encoded = $response->json('content');
        if ($response->json('encoding') === 'base64' && is_string($encoded) && $encoded !== '') {
            return base64_decode(preg_replace('/\s+/', '', $encoded), true) ?: null;
        }

        $sha = (string) $response->json('sha');
        if (preg_match('/\A[a-f0-9]{40}\z/i', $sha) !== 1) {
            return null;
        }
        $blob = $client->get($base.'/git/blobs/'.$sha)->throw();
        $encoded = $blob->json('content');

        return $blob->json('encoding') === 'base64' && is_string($encoded)
            ? (base64_decode(preg_replace('/\s+/', '', $encoded), true) ?: null)
            : null;
    }

    private function cacheKey(Pagina $page): ?string
    {
        $path = $page->logo_path;
        if ($page->logo_disk !== 'supabase' || ! is_string($path) || $path === ''
            || str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, '\\')
            || preg_match('~^https?://~i', $path)) {
            return null;
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return null;
        }

        return 'logo-cache/'.hash('sha256', $path).'.'.$extension;
    }

    private function missingKey(string $key): string
    {
        return 'logo-missing:'.hash('sha256', $key);
    }
}
