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
            [$payload, $logos] = $this->payloadAndLogos();
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

    /** @return array{0: array<string, mixed>, 1: list<array{path: string, contents: string}>} */
    private function payloadAndLogos(): array
    {
        $payload = $this->exporter->payload();
        $logos = [];
        $disks = Pagina::query()->whereIn('slug', array_column($payload['pages'], 'slug'))->pluck('logo_disk', 'slug');

        foreach ($payload['pages'] as &$page) {
            $logo = $page['logo'] ?? null;
            if (! is_string($logo) || ! str_starts_with($logo, '/storage/')) {
                continue;
            }

            $source = ltrim(Str::after($logo, '/storage/'), '/');
            $disk = Storage::disk($disks->get($page['slug']));
            if (! $disk->exists($source)) {
                throw new RuntimeException("No se encontró el logo público: {$source}.");
            }

            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION)) ?: 'bin';
            if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true)) {
                throw new RuntimeException("Formato de logo no permitido: .{$extension}.");
            }

            $path = 'public/generated/logos/'.$page['slug'].'.'.$extension;
            $logos[] = ['path' => $path, 'contents' => $disk->get($source)];
            $publicLogo = '/generated/logos/'.$page['slug'].'.'.$extension;
            $page['logo'] = $publicLogo;
            if (is_string($page['favicon'] ?? null) && str_starts_with($page['favicon'], '/storage/')) {
                $page['favicon'] = $publicLogo;
            }
        }
        unset($page);

        return [$payload, $logos];
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
