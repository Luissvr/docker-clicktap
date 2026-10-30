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
        abort_unless(auth()->user()?->activo && $page->activo && $page->papelera_en === null, 403);
        $allowed = config('clicktap.publication_slugs');
        abort_if(is_array($allowed) && ! in_array($page->slug, $allowed, true), 422, 'Esta página no está incluida en la selección de publicación.');
        $this->enqueue($page, 'publish');
    }

    public function requestWithdrawal(Pagina $page): void
    {
        abort_unless(auth()->user()?->activo && ! $page->activo, 403);
        $this->enqueue($page, 'withdraw');
    }

    private function enqueue(Pagina $page, string $type): void
    {
        $cache = $this->cache();
        $cache->lock('publication-state-lock', 10)->block(2, function () use ($cache, $page, $type): void {
            $tasks = $this->pendingTasks($cache);
            $tasks = array_values(array_filter($tasks, fn (array $task): bool => $task['page_id'] !== $page->getKey() || ($task['type'] ?? 'publish') === $type));
            foreach ($tasks as $task) {
                if ($task['page_id'] === $page->getKey() && ($task['type'] ?? 'publish') === $type) {
                    return;
                }
            }
            $tasks[] = [
                'id' => (string) Str::uuid(), 'page_id' => $page->getKey(),
                'requested_at' => now()->toIso8601String(), 'name' => $page->nombre_publico,
                'type' => $type,
                'events' => [['stage' => 'received', 'at' => now()->toIso8601String(), 'message' => 'Solicitud recibida por el servidor']],
            ];
            $cache->forever('publication-pending', $tasks);
        });
    }

    public function cancelPendingPublication(string $pageId): bool
    {
        $cache = $this->cache();

        return $cache->lock('publication-state-lock', 10)->block(2, function () use ($cache, $pageId): bool {
            $tasks = array_values(array_filter(
                $this->pendingTasks($cache),
                fn (array $task): bool => $task['page_id'] !== $pageId || ($task['type'] ?? 'publish') !== 'publish',
            ));
            if ($tasks === []) {
                $cache->forget('publication-pending');
            } else {
                $cache->forever('publication-pending', $tasks);
            }
            $running = $cache->get('publication-running');

            return ($running['page_id'] ?? null) === $pageId && ($running['type'] ?? 'publish') === 'publish';
        });
    }

    public function status(): array
    {
        $cache = $this->cache();
        $pending = $this->pendingTasks($cache);
        $heartbeat = $cache->get('publication-worker-heartbeat');
        $tracker = app(CloudflareDeploymentTracker::class);

        return [
            'pending' => $pending[0] ?? null,
            'pendingCount' => count($pending),
            'running' => $cache->get('publication-running'),
            'result' => $cache->get('publication-result'),
            'tracking' => $tracker->hasPending(),
            'trackingTask' => $tracker->current(),
            'workerAvailable' => $heartbeat !== null,
            'workerHeartbeatAt' => is_string($heartbeat) ? $heartbeat : null,
        ];
    }

    public function pageStatus(string $pageId): ?array
    {
        $cache = $this->cache();
        $running = $cache->get('publication-running');
        if (($running['page_id'] ?? null) === $pageId) {
            return $running + ['state' => 'procesando'];
        }
        foreach ($this->pendingTasks($cache) as $task) {
            if ($task['page_id'] === $pageId) {
                return $task + ['state' => 'pendiente'];
            }
        }

        $tracking = app(CloudflareDeploymentTracker::class)->pageStatus($pageId);
        if ($tracking) {
            return $tracking;
        }

        return $cache->get('publication-result:'.$pageId);
    }

    public function work(): void
    {
        $cache = $this->cache();
        $cache->put('publication-worker-heartbeat', now()->toIso8601String(), 180);
        $cache->lock('publication-run-lock', 1800)->get(function () use ($cache): void {
            if ($this->pendingTasks($cache) === []) {
                $this->recoverPendingWithdrawals($cache);
            }
            $task = $cache->lock('publication-state-lock', 10)->block(2, function () use ($cache): ?array {
                if ($cache->has('publication-running')) {
                    $interrupted = $cache->pull('publication-running');
                    $cache->forever('publication-result', $interrupted + ['state' => 'interrumpida', 'message' => 'El proceso se interrumpió. Comprueba el repositorio antes de repetir la publicación.']);
                }
                $tasks = $this->pendingTasks($cache);
                $task = array_shift($tasks);
                if ($task) {
                    $startedAt = now()->toIso8601String();
                    $task['started_at'] = $startedAt;
                    $task['stage'] = 'started';
                    $task['stage_message'] = 'El worker tomó la solicitud';
                    $task['stage_at'] = $startedAt;
                    $task['events'][] = ['stage' => 'started', 'at' => $startedAt, 'message' => $task['stage_message']];
                    $cache->forever('publication-running', $task);
                    if ($tasks === []) {
                        $cache->forget('publication-pending');
                    } else {
                        $cache->forever('publication-pending', $tasks);
                    }
                }

                return $task;
            });
            if (! $task) {
                app(CloudflareDeploymentTracker::class)->check();

                return;
            }
            try {
                $page = CatalogConnection::remoteReads(fn () => Pagina::findOrFail($task['page_id']));
                $withdraw = ($task['type'] ?? 'publish') === 'withdraw';
                if ($withdraw) {
                    if ($page->activo) {
                        throw new \RuntimeException('La página volvió a activarse; se detuvo la retirada anterior.');
                    }
                    $commit = app(PublicPagePublisher::class)->withdraw($page, fn (string $stage, string $message) => $this->reportStage($task['id'], $stage, $message));
                    $result = ['state' => 'retirada_enviada', 'commit' => $commit, 'message' => 'Retirada enviada al repositorio. El acceso público aún no está verificado como retirado.'];
                } else {
                    if (! $page->activo) {
                        throw new \RuntimeException('La página seleccionada ya no está lista para publicar.');
                    }
                    $commit = app(PublicPagePublisher::class)->publish($page, fn (string $stage, string $message) => $this->reportStage($task['id'], $stage, $message));
                    $result = ['state' => 'enviada', 'commit' => $commit, 'message' => 'Enviada al repositorio. El despliegue de Cloudflare todavía no está confirmado.'];
                }
            } catch (Throwable $exception) {
                report($exception);
                $withdraw = ($task['type'] ?? 'publish') === 'withdraw';
                $result = [
                    'state' => $withdraw ? 'retirada_fallida' : 'fallida',
                    'message' => $withdraw
                        ? 'No se pudo retirar el acceso. La página puede seguir visible; revisa la conexión y reintenta.'
                        : 'No se pudo confirmar el envío. Comprueba el repositorio y la conexión antes de reintentar.',
                ];
            }
            $completed = $cache->lock('publication-state-lock', 10)->block(2, function () use ($cache, $task, $result): array {
                $completed = array_merge($cache->get('publication-running', $task), $result, ['completed_at' => now()->toIso8601String()]);
                if (in_array($result['state'], ['fallida', 'retirada_fallida'], true)) {
                    $completed['events'][] = ['stage' => 'failed', 'at' => $completed['completed_at'], 'message' => 'El worker terminó con un error'];
                }
                $cache->forever('publication-result', $completed);
                $cache->forever('publication-result:'.$task['page_id'], $completed);
                $cache->forget('publication-running');

                return $completed;
            });
            if (isset($commit, $page) && in_array($result['state'], ['enviada', 'retirada_enviada'], true)) {
                app(CloudflareDeploymentTracker::class)->track($page->fresh(), $commit, ($task['type'] ?? 'publish'), $completed);
            }
            app(CloudflareDeploymentTracker::class)->check();
        });
    }

    private function reportStage(string $taskId, string $stage, string $message): void
    {
        try {
            $cache = $this->cache();
            $cache->lock('publication-state-lock', 10)->block(2, function () use ($cache, $taskId, $stage, $message): void {
                $running = $cache->get('publication-running');
                if (($running['id'] ?? null) !== $taskId) {
                    return;
                }
                $at = now()->toIso8601String();
                $running['stage'] = $stage;
                $running['stage_message'] = $message;
                $running['stage_at'] = $at;
                $running['events'][] = ['stage' => $stage, 'at' => $at, 'message' => $message];
                $cache->forever('publication-running', $running);
                $cache->put('publication-worker-heartbeat', $at, 180);
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @return list<array{id: string, page_id: string, requested_at: string, name: string}> */
    private function pendingTasks(Repository $cache): array
    {
        $pending = $cache->get('publication-pending', []);

        return isset($pending['page_id']) ? [$pending] : $pending;
    }

    private function recoverPendingWithdrawals(Repository $cache): void
    {
        if (! $cache->add('publication-withdrawal-recovery-due', true, 60)) {
            return;
        }

        try {
            $pages = CatalogConnection::remoteReads(fn () => Pagina::query()
                ->where('activo', false)
                ->where('publicacion_estado', 'retirada_pendiente')
                ->limit(20)
                ->get());
            foreach ($pages as $page) {
                $this->enqueue($page, 'withdraw');
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
