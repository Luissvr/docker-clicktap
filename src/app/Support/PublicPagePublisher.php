<?php

namespace App\Support;

use App\Models\Pagina;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class PublicPagePublisher
{
    public function __construct(private readonly PublicPageExporter $exporter) {}

    public function publish(Pagina $pagina, ?Closure $progress = null): string
    {
        $slugs = config('clicktap.publication_slugs');
        if (! $pagina->activo || $pagina->papelera_en !== null || (is_array($slugs) && ! in_array($pagina->slug, $slugs, true))) {
            throw new RuntimeException('Esta página no está incluida en la selección de publicación.');
        }
        try {
            return CatalogConnection::remoteReads(fn (): string => $this->publishFromRemote($pagina->fresh(), false, $progress));
        } finally {
            CatalogConnection::refreshAfterWrite($pagina);
        }
    }

    public function withdraw(Pagina $pagina, ?Closure $progress = null): string
    {
        if ($pagina->activo) {
            throw new RuntimeException('Desactiva la página antes de solicitar su retirada.');
        }

        try {
            return CatalogConnection::remoteReads(fn (): string => $this->publishFromRemote($pagina->fresh(), true, $progress));
        } finally {
            CatalogConnection::refreshAfterWrite($pagina);
        }
    }

    private function publishFromRemote(Pagina $pagina, bool $withdraw = false, ?Closure $progress = null): string
    {
        if (! $withdraw && (! $pagina->activo || $pagina->papelera_en !== null)) {
            throw new RuntimeException('Esta página ya no está disponible para publicar.');
        }

        $pagina->forceFill([
            'publicacion_estado' => $withdraw ? 'retirando' : 'publicando',
            'publicacion_error' => null,
        ])->save();

        try {
            $progress?->__invoke('repository', 'Conectando con el repositorio público');
            $token = (string) config('services.github.token');
            $repository = trim((string) config('services.github.repository'));
            $branch = trim((string) config('services.github.branch', 'main'));
            $apiUrl = rtrim((string) config('services.github.api_url', 'https://api.github.com'), '/');

            if ($token === '' || $repository === '' || $branch === '') {
                throw new RuntimeException('Configura GITHUB_API_TOKEN, GITHUB_REPOSITORY y GITHUB_PUBLICATION_BRANCH.');
            }

            $client = $this->client($token);
            $baseUrl = $apiUrl.'/repos/'.$repository;
            $head = $client->get($baseUrl.'/git/ref/heads/'.$branch)->throw()->json('object.sha');
            $baseTree = $client->get($baseUrl.'/git/commits/'.$head)->throw()->json('tree.sha');
            $existing = $this->readContract($client, $baseUrl, $head);
            $progress?->__invoke('preparing', $withdraw ? 'Preparando la retirada de la página' : 'Preparando los archivos de la página');
            $payload = $withdraw
                ? $this->removePageFromContract($existing, $pagina)
                : $this->mergePageIntoContract($existing, $pagina);
            $logos = $withdraw ? [] : $this->pageLogo($pagina);
            $manualRoutes = $withdraw ? $this->manualRoutes($client, $baseUrl, $head, $pagina) : [];
            if ($withdraw && count($payload['pages']) === count($existing['pages']) && $manualRoutes === []) {
                $pagina->forceFill(['publicacion_estado' => 'retirada_enviada', 'publicacion_commit' => $head, 'publicacion_error' => null])->save();
                $progress?->__invoke('repository_sent', 'La página ya no figura en el repositorio');

                return (string) $head;
            }
            $contract = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
            $entries = [[
                'path' => 'src/data/generated/pages.json',
                'mode' => '100644',
                'type' => 'blob',
                'sha' => $this->blob($client, $baseUrl, $contract),
            ]];

            foreach ($logos as $logo) {
                $entries[] = [
                    'path' => $logo['path'],
                    'mode' => '100644',
                    'type' => 'blob',
                    'sha' => $this->blob($client, $baseUrl, $logo['contents']),
                ];
            }
            foreach ($manualRoutes as $route) {
                $entries[] = [
                    'path' => $route,
                    'mode' => '100644',
                    'type' => 'blob',
                    'sha' => null,
                ];
            }

            $progress?->__invoke('sending', 'Enviando los cambios al repositorio');
            $tree = $client->post($baseUrl.'/git/trees', [
                'base_tree' => $baseTree,
                'tree' => $entries,
            ])->throw()->json('sha');
            $commit = $client->post($baseUrl.'/git/commits', [
                'message' => $withdraw ? 'retirar página "'.$pagina->slug.'"' : $this->commitMessage($pagina),
                'tree' => $tree,
                'parents' => [$head],
            ])->throw()->json('sha');
            $client->patch($baseUrl.'/git/refs/heads/'.$branch, [
                'sha' => $commit,
                'force' => false,
            ])->throw();

            $pagina->forceFill([
                'publicacion_estado' => $withdraw ? 'retirada_enviada' : 'enviada',
                'publicacion_commit' => $commit,
                'publicacion_error' => null,
                'publicada_en' => $withdraw ? $pagina->publicada_en : now(),
            ])->save();
            $progress?->__invoke('repository_sent', $withdraw ? 'Retirada enviada al repositorio' : 'Publicación enviada al repositorio');

            return (string) $commit;
        } catch (\Throwable $exception) {
            $pagina->forceFill([
                'publicacion_estado' => $withdraw ? 'retirada_fallida' : 'fallida',
                'publicacion_error' => Str::limit($exception->getMessage(), 2000),
            ])->save();

            throw $exception;
        }
    }

    private function commitMessage(Pagina $pagina): string
    {
        $name = trim((string) $pagina->nombre_publico) ?: $pagina->slug;
        $name = Str::of($name)
            ->replace(["\r", "\n", '"'], '')
            ->squish()
            ->limit(80, '')
            ->toString();
        $action = $pagina->publicacion_commit ? 'actualizar' : 'añadir';

        return "{$action} página \"{$name}\" ({$pagina->slug})";
    }

    /** @return array<string, mixed> */
    private function readContract(PendingRequest $client, string $baseUrl, string $head): array
    {
        $response = $client->get($baseUrl.'/contents/src/data/generated/pages.json', ['ref' => $head])->throw();
        $content = base64_decode((string) $response->json('content'), true);
        if ($content === false) {
            throw new RuntimeException('No se pudo leer el catálogo actual del repositorio.');
        }

        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || ($payload['version'] ?? null) !== 1 || ! is_array($payload['pages'] ?? null) || ! array_is_list($payload['pages'])) {
            throw new RuntimeException('El catálogo actual del repositorio no tiene un formato válido.');
        }

        $slugs = [];
        foreach ($payload['pages'] as $page) {
            $slug = is_array($page) ? ($page['slug'] ?? null) : null;
            if (! is_string($slug) || trim($slug) === '' || in_array($slug, $slugs, true)) {
                throw new RuntimeException('El catálogo actual del repositorio contiene rutas inválidas o repetidas.');
            }
            $slugs[] = $slug;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function mergePageIntoContract(array $payload, Pagina $pagina): array
    {
        $page = $this->exporter->page($pagina);
        if (is_string($page['logo'] ?? null) && str_starts_with($page['logo'], '/storage/')) {
            $extension = strtolower(pathinfo($pagina->logo_path, PATHINFO_EXTENSION)) ?: 'bin';
            $publicLogo = '/generated/logos/'.$pagina->slug.'.'.$extension;
            $page['logo'] = $publicLogo;
            if (is_string($page['favicon'] ?? null) && str_starts_with($page['favicon'], '/storage/')) {
                $page['favicon'] = $publicLogo;
            }
        }
        foreach ($payload['pages'] as $index => $existing) {
            if ($existing['slug'] === $page['slug']) {
                $payload['pages'][$index] = $page;

                return $payload;
            }
        }
        $payload['pages'][] = $page;

        return $payload;
    }

    /** @return array<string, mixed> */
    private function removePageFromContract(array $payload, Pagina $pagina): array
    {
        $payload['pages'] = array_values(array_filter(
            $payload['pages'],
            fn (array $existing): bool => $existing['slug'] !== $pagina->slug,
        ));

        return $payload;
    }

    /** @return list<string> */
    private function manualRoutes(PendingRequest $client, string $baseUrl, string $head, Pagina $page): array
    {
        $routes = [];
        $slugs = array_merge([$page->slug], $page->getConnection()->table('alias_pagina')
            ->where('pagina_id', $page->getKey())->pluck('ruta')->all());
        foreach ($slugs as $slug) {
            foreach (["src/pages/{$slug}.astro", "src/pages/{$slug}/index.astro"] as $path) {
                $response = $client->get($baseUrl.'/contents/'.$path, ['ref' => $head]);
                if ($response->status() === 404) {
                    continue;
                }
                $response->throw();
                if ($response->json('type') !== 'file') {
                    throw new RuntimeException("La ruta pública {$path} necesita revisión manual.");
                }
                $routes[] = $path;
            }
        }

        return $routes;
    }

    /** @return list<array{path: string, contents: string}> */
    private function pageLogo(Pagina $pagina): array
    {
        $logo = $pagina->logo_path;
        if (! is_string($logo) || trim($logo) === '' || preg_match('/^https?:\/\//i', $logo) === 1) {
            return [];
        }

        $source = ltrim($logo, '/');
        $disk = Storage::disk($pagina->logo_disk);
        if (! $disk->exists($source)) {
            throw new RuntimeException("No se encontró el logo público: {$source}.");
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION)) ?: 'bin';
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true)) {
            throw new RuntimeException("Formato de logo no permitido: .{$extension}.");
        }

        return [[
            'path' => 'public/generated/logos/'.$pagina->slug.'.'.$extension,
            'contents' => $disk->get($source),
        ]];
    }

    private function client(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['User-Agent' => 'ClickTap'])
            ->timeout(30);
    }

    private function blob(PendingRequest $client, string $baseUrl, string $contents): string
    {
        return app(CatalogSyncState::class)->cache()->remember('github-blob:'.hash('sha256', $baseUrl.'|'.$contents), 3600,
            fn (): string => (string) $client->post($baseUrl.'/git/blobs', [
                'content' => base64_encode($contents),
                'encoding' => 'base64',
            ])->throw()->json('sha'));
    }
}
