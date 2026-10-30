<?php

namespace App\Support;

use App\Models\Pagina;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Http;
use Throwable;

class CloudflareDeploymentTracker
{
    private function cache(): Repository
    {
        return cache()->store(config('clicktap.publication_store', 'publications'));
    }

    private function configured(): bool
    {
        return filled(config('services.cloudflare.account_id'))
            && filled(config('services.cloudflare.pages_project'))
            && filled(config('services.cloudflare.token'));
    }

    public function track(Pagina $page, string $commit, string $type, array $origin = []): void
    {
        if (! $this->configured()) {
            return;
        }

        $cache = $this->cache();
        $cache->lock('publication-tracking-lock', 10)->block(2, function () use ($cache, $page, $commit, $type, $origin): void {
            $trackingStartedAt = now()->toIso8601String();
            $tasks = collect($cache->get('publication-tracking', []))
                ->reject(fn (array $task): bool => $task['page_id'] === $page->getKey())
                ->values()->all();
            $events = $origin['events'] ?? [];
            $events[] = ['stage' => 'cloudflare_check', 'at' => $trackingStartedAt, 'message' => 'Cloudflare: esperando confirmación del despliegue'];
            $tasks[] = [
                'page_id' => $page->getKey(),
                'commit' => $commit,
                'type' => $type,
                'revision' => (int) $page->revision,
                'name' => $origin['name'] ?? $page->nombre_publico,
                'requested_at' => $origin['requested_at'] ?? $trackingStartedAt,
                'started_at' => $origin['started_at'] ?? null,
                'repository_sent_at' => $origin['completed_at'] ?? $trackingStartedAt,
                'events' => $events,
                'next_check_at' => now()->timestamp + 5,
                'attempts' => 0,
            ];
            $cache->forever('publication-tracking', $tasks);
        });
    }

    public function pageStatus(string $pageId): ?array
    {
        foreach ($this->cache()->get('publication-tracking', []) as $task) {
            if ($task['page_id'] === $pageId) {
                return $task + ['state' => 'comprobando_cloudflare'];
            }
        }

        return null;
    }

    public function hasPending(): bool
    {
        return $this->cache()->get('publication-tracking', []) !== [];
    }

    public function current(): ?array
    {
        return $this->cache()->get('publication-tracking', [])[0] ?? null;
    }

    public function check(): void
    {
        if (! $this->configured()) {
            return;
        }

        $cache = $this->cache();
        foreach ($cache->get('publication-tracking', []) as $task) {
            if (($task['next_check_at'] ?? 0) > now()->timestamp) {
                continue;
            }
            try {
                $outcome = $this->inspect($task);
            } catch (Throwable $exception) {
                report($exception);
                $outcome = null;
            }
            $cache->lock('publication-tracking-lock', 10)->block(2, function () use ($cache, $task, $outcome): void {
                $tasks = $cache->get('publication-tracking', []);
                foreach ($tasks as $index => $current) {
                    if ($current['page_id'] !== $task['page_id'] || $current['commit'] !== $task['commit']) {
                        continue;
                    }
                    if ($outcome !== null) {
                        unset($tasks[$index]);
                        $completed = array_merge($task, $outcome, ['completed_at' => now()->toIso8601String()]);
                        $completed['events'][] = [
                            'stage' => $outcome['state'],
                            'at' => $completed['completed_at'],
                            'message' => $outcome['message'],
                        ];
                        $cache->forever('publication-result:'.$task['page_id'], $completed);
                        $latest = $cache->get('publication-result');
                        if (($latest['page_id'] ?? null) === $task['page_id'] && ($latest['commit'] ?? null) === $task['commit']) {
                            $cache->forever('publication-result', array_merge($latest, $outcome, ['completed_at' => $completed['completed_at'], 'events' => $completed['events']]));
                        }
                    } else {
                        $current['attempts']++;
                        $current['last_checked_at'] = now()->toIso8601String();
                        if ($current['attempts'] >= 30) {
                            unset($tasks[$index]);
                            $timedOut = $task + [
                                'state' => 'comprobacion_pendiente',
                                'message' => 'No se pudo confirmar el resultado público. Consulta Cloudflare antes de repetir.',
                                'completed_at' => now()->toIso8601String(),
                            ];
                            $timedOut['events'][] = ['stage' => 'comprobacion_pendiente', 'at' => $timedOut['completed_at'], 'message' => $timedOut['message']];
                            $cache->forever('publication-result:'.$task['page_id'], $timedOut);
                            $latest = $cache->get('publication-result');
                            if (($latest['page_id'] ?? null) === $task['page_id'] && ($latest['commit'] ?? null) === $task['commit']) {
                                $cache->forever('publication-result', array_merge($latest, [
                                    'state' => $timedOut['state'],
                                    'message' => $timedOut['message'],
                                    'completed_at' => $timedOut['completed_at'],
                                    'events' => $timedOut['events'],
                                ]));
                            }
                        } else {
                            $current['next_check_at'] = now()->timestamp + min(60, 5 * $current['attempts']);
                            $tasks[$index] = $current;
                        }
                    }
                    $cache->forever('publication-tracking', array_values($tasks));

                    return;
                }
            });
        }
    }

    /** @param array<string, mixed> $task
     * @return array{state: string, message: string}|null
     */
    private function inspect(array $task): ?array
    {
        $page = CatalogConnection::remoteReads(fn (): ?Pagina => Pagina::find($task['page_id']));
        if (! $page || $page->publicacion_commit !== $task['commit'] || (int) $page->revision !== $task['revision']) {
            return ['state' => 'sustituida', 'message' => 'Otra revisión reemplazó esta operación.'];
        }

        $account = rawurlencode((string) config('services.cloudflare.account_id'));
        $project = rawurlencode((string) config('services.cloudflare.pages_project'));
        $response = Http::withToken((string) config('services.cloudflare.token'))
            ->acceptJson()->timeout(10)
            ->get("https://api.cloudflare.com/client/v4/accounts/{$account}/pages/projects/{$project}/deployments", [
                'env' => 'production', 'per_page' => 25,
            ])->throw();
        $deployment = collect($response->json('result', []))->first(fn (array $item): bool => ($item['deployment_trigger']['metadata']['commit_hash'] ?? null) === $task['commit']
                && ($item['environment'] ?? null) === 'production');
        if (! $deployment) {
            return null;
        }
        $stage = $deployment['latest_stage']['status'] ?? null;
        if (in_array($stage, ['failure', 'canceled', 'skipped'], true)) {
            $page->forceFill(['publicacion_estado' => $task['type'] === 'withdraw' ? 'retirada_fallida' : 'fallida'])->save();
            CatalogConnection::refreshAfterWrite($page);

            return ['state' => 'fallida', 'message' => 'Cloudflare no pudo completar el despliegue. Revisa el proyecto antes de reintentar.'];
        }
        if ($stage !== 'success') {
            return null;
        }

        $withdraw = $task['type'] === 'withdraw';
        $baseUrl = rtrim((string) config('services.cloudflare.public_url', 'https://clicktap.app'), '/');
        $routes = [$page->slug];
        if ($withdraw) {
            $routes = array_merge($routes, $page->getConnection()->table('alias_pagina')
                ->where('pagina_id', $page->getKey())->pluck('ruta')->all());
        }
        foreach ($routes as $route) {
            $public = Http::timeout(10)->withoutRedirecting()->get($baseUrl.'/'.rawurlencode($route));
            if ($withdraw ? ! in_array($public->status(), [404, 410], true) : ! $public->ok()) {
                return null;
            }
        }

        $page->forceFill([
            'publicacion_estado' => $withdraw ? 'retirada' : 'publicada',
            'publicacion_error' => null,
        ])->save();
        CatalogConnection::refreshAfterWrite($page);

        return [
            'state' => $withdraw ? 'retirada' : 'publicada',
            'message' => $withdraw ? 'El dominio público ya no entrega esta página.' : 'El despliegue coincide con el commit y la página responde desde el dominio público.',
        ];
    }
}
