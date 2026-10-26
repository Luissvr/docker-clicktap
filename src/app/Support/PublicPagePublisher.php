<?php

namespace App\Support;

use App\Models\Pagina;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class PublicPagePublisher
{
    public function __construct(private readonly PublicPageExporter $exporter) {}

    public function publish(Pagina $pagina): string
    {
        try {
            return CatalogConnection::remoteReads(fn (): string => $this->publishFromRemote($pagina->fresh()));
        } finally {
            CatalogConnection::refreshAfterWrite($pagina);
        }
    }

    private function publishFromRemote(Pagina $pagina): string
    {
        $pagina->forceFill([
            'publicacion_estado' => 'publicando',
            'publicacion_error' => null,
        ])->save();

        try {
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
            $payload = $this->mergePageIntoContract(
                $this->readContract($client, $baseUrl, $branch),
                $pagina,
            );
            $logos = $this->pageLogo($pagina);
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

            $tree = $client->post($baseUrl.'/git/trees', [
                'base_tree' => $baseTree,
                'tree' => $entries,
            ])->throw()->json('sha');
            $commit = $client->post($baseUrl.'/git/commits', [
                'message' => $this->commitMessage($pagina),
                'tree' => $tree,
                'parents' => [$head],
            ])->throw()->json('sha');
            $client->patch($baseUrl.'/git/refs/heads/'.$branch, [
                'sha' => $commit,
                'force' => false,
            ])->throw();

            $pagina->forceFill([
                'publicacion_estado' => 'enviada',
                'publicacion_commit' => $commit,
                'publicacion_error' => null,
                'publicada_en' => now(),
            ])->save();

            return (string) $commit;
        } catch (\Throwable $exception) {
            $pagina->forceFill([
                'publicacion_estado' => 'fallida',
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
    private function readContract(PendingRequest $client, string $baseUrl, string $branch): array
    {
        $response = $client->get($baseUrl.'/contents/src/data/generated/pages.json', ['ref' => $branch])->throw();
        $content = base64_decode((string) $response->json('content'), true);
        if ($content === false) {
            throw new RuntimeException('No se pudo leer el catálogo actual del repositorio.');
        }

        $payload = json_decode($content, true);
        if (! is_array($payload) || ! isset($payload['pages']) || ! is_array($payload['pages'])) {
            throw new RuntimeException('El catálogo actual del repositorio no tiene un formato válido.');
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
        $pages = collect($payload['pages'])
            ->reject(fn (mixed $existing): bool => is_array($existing) && ($existing['slug'] ?? null) === $page['slug'])
            ->push($page)
            ->sortBy('slug')
            ->values()
            ->all();

        return ['version' => (int) ($payload['version'] ?? 1), 'pages' => $pages];
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
