<?php

namespace App\Support;

use App\Models\Pagina;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;
use Throwable;

class PublicationQueue
{
    private function cache(): Repository
    {
        return cache()->store(config('clicktap.publication_store', 'publications'));
    }

    public function request(Pagina $page): void
    {
        abort_unless(auth()->user()?->activo && $page->activo, 403);
        $cache = $this->cache();
        $cache->lock('publication-state-lock', 10)->block(2, function () use ($cache, $page): void {
            // Every export contains all active pages, so pending requests can be combined.
            $cache->forever('publication-pending', [
                'id' => (string) Str::uuid(), 'page_id' => $page->getKey(),
                'requested_at' => now()->toIso8601String(), 'name' => $page->nombre_publico,
            ]);
        });
    }

    public function status(): array
    {
        $cache = $this->cache();

        return [
            'pending' => $cache->get('publication-pending'),
            'running' => $cache->get('publication-running'),
            'result' => $cache->get('publication-result'),
            'workerAvailable' => $cache->has('publication-worker-heartbeat'),
        ];
    }

    public function work(): void
    {
        $cache = $this->cache();
        $cache->put('publication-worker-heartbeat', true, 30);
        $cache->lock('publication-run-lock', 1800)->get(function () use ($cache): void {
            $task = $cache->lock('publication-state-lock', 10)->block(2, function () use ($cache): ?array {
                if ($cache->has('publication-running')) {
                    $interrupted = $cache->pull('publication-running');
                    $cache->forever('publication-result', $interrupted + ['state' => 'interrumpida', 'message' => 'El proceso se interrumpió. Comprueba el repositorio antes de repetir la publicación.']);
                }
                $task = $cache->get('publication-pending');
                if ($task) {
                    $cache->forever('publication-running', $task + ['started_at' => now()->toIso8601String()]);
                    $cache->forget('publication-pending');
                }

                return $task;
            });
            if (! $task) {
                return;
            }
            try {
                $page = CatalogConnection::remoteReads(fn () => Pagina::findOrFail($task['page_id']));
                if (! $page->activo) {
                    throw new \RuntimeException('La página seleccionada ya no está lista para publicar.');
                }
                $commit = app(PublicPagePublisher::class)->publish($page);
                $result = ['state' => 'enviada', 'commit' => $commit, 'message' => 'Enviada al repositorio. El despliegue de Cloudflare todavía no está confirmado.'];
            } catch (Throwable $exception) {
                report($exception);
                $result = ['state' => 'fallida', 'message' => 'No se pudo confirmar el envío. Comprueba el repositorio y la conexión antes de reintentar.'];
            }
            $cache->lock('publication-state-lock', 10)->block(2, function () use ($cache, $task, $result): void {
                $cache->forever('publication-result', $task + $result + ['completed_at' => now()->toIso8601String()]);
                $cache->forget('publication-running');
            });
        });
    }
}
