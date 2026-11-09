<?php

namespace App\Support\Research;

use App\Exceptions\BusinessResearchException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cola de investigaciones de negocios. Guarda cada tarea en el mismo almacén que la cola de publicación
 * y la procesa el worker `clicktap:research-worker`, sin bloquear la pantalla.
 */
class BusinessResearchQueue
{
    private const TTL_HOURS = 24;

    private const ACTIVE_STATES = ['buscando', 'investigando'];

    public function __construct(
        private GoogleMapsCandidateSearch $candidates,
        private BusinessInvestigator $investigator,
    ) {}

    private function cache(): Repository
    {
        return cache()->store(config('clicktap.publication_store', 'publications'));
    }

    /**
     * @return array<string, mixed>
     */
    public function start(string $userId, string $name, string $city): array
    {
        $now = now()->toIso8601String();
        $job = [
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'estado' => 'buscando',
            'consulta' => $name,
            'ciudad' => $city,
            'candidatos' => [],
            'elegido' => null,
            'pasos' => [],
            'campos' => [],
            'pendientes' => [],
            'descartados' => [],
            'error' => null,
            'creado' => $now,
            'actualizado' => $now,
        ];
        $this->save($job);
        $this->push($job['id'], 'search');

        return $job;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function choose(string $jobId, int $index): ?array
    {
        $job = $this->find($jobId);
        if ($job === null || $job['estado'] !== 'esperando_eleccion' || ! isset($job['candidatos'][$index])) {
            return null;
        }

        $job['elegido'] = $job['candidatos'][$index];
        $job['estado'] = 'investigando';
        $job['pasos'] = BusinessInvestigator::initialSteps();
        $this->save($job);
        $this->push($jobId, 'investigate');

        return $job;
    }

    /**
     * Registra un perfil social que el administrador encontró y pegó a mano.
     */
    public function setManualProfile(string $jobId, string $network, string $url): bool
    {
        $job = $this->find($jobId);
        if ($job === null || $job['estado'] !== 'lista' || ! in_array($network, ['instagram', 'facebook', 'tiktok'], true)) {
            return false;
        }

        $job['campos'][$network] = ['valor' => $url, 'verificado' => true, 'origen' => 'manual', 'fuente' => $url];
        foreach ($job['pasos'] as &$step) {
            if ($step['clave'] === $network) {
                $step['estado'] = 'encontrado';
                $step['detalle'] = 'Ingresado manualmente por ti';
            }
        }
        unset($step);
        $this->save($job);

        return true;
    }

    public function cancel(string $jobId): void
    {
        $job = $this->find($jobId);
        if ($job !== null && in_array($job['estado'], [...self::ACTIVE_STATES, 'esperando_eleccion'], true)) {
            $job['estado'] = 'cancelada';
            $this->save($job);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $jobId): ?array
    {
        $job = $this->cache()->get($this->key($jobId));

        return is_array($job) ? $job : null;
    }

    public function isActive(?array $job): bool
    {
        return $job !== null && in_array($job['estado'], self::ACTIVE_STATES, true);
    }

    /**
     * @return array{workerAvailable: bool, pendingCount: int}
     */
    public function status(): array
    {
        $cache = $this->cache();

        return [
            'workerAvailable' => $cache->get('research-worker-heartbeat') !== null,
            'pendingCount' => count($cache->get('research-pending', [])),
        ];
    }

    /**
     * Procesa una tarea pendiente. Devuelve false si no había ninguna.
     */
    public function work(): bool
    {
        $cache = $this->cache();
        $cache->put('research-worker-heartbeat', now()->toIso8601String(), 30);

        $task = $cache->lock('research-state-lock', 10)->block(2, function () use ($cache): ?array {
            $pending = $cache->get('research-pending', []);
            $task = array_shift($pending);
            $cache->forever('research-pending', $pending);

            return $task;
        });
        if ($task === null) {
            return false;
        }

        $job = $this->find($task['id']);
        if ($job === null || in_array($job['estado'], ['cancelada', 'lista'], true)) {
            return true;
        }

        try {
            if ($task['action'] === 'search') {
                $this->runSearch($job);
            } else {
                $this->runInvestigation($job);
            }
        } catch (BusinessResearchException $exception) {
            $this->fail($task['id'], $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($task['id'], 'Ocurrió un error inesperado durante la investigación.');
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function runSearch(array $job): void
    {
        $candidates = $this->candidates->search($job['consulta'], $job['ciudad']);
        $job = $this->find($job['id']) ?? $job;
        if ($job['estado'] === 'cancelada') {
            return;
        }

        $job['candidatos'] = $candidates;
        $job['estado'] = $candidates === [] ? 'sin_resultados' : 'esperando_eleccion';
        $this->save($job);
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function runInvestigation(array $job): void
    {
        $result = $this->investigator->investigate($job, function (array $progress): void {
            $current = $this->find($progress['id']);
            if ($current === null || $current['estado'] === 'cancelada') {
                throw new BusinessResearchException('La investigación fue cancelada.');
            }
            $this->save($progress);
        });
        $this->save($result);
    }

    private function fail(string $jobId, string $message): void
    {
        $job = $this->find($jobId);
        if ($job === null || $job['estado'] === 'cancelada') {
            return;
        }
        $job['estado'] = 'fallida';
        $job['error'] = $message;
        $this->save($job);
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function save(array &$job): void
    {
        $job['actualizado'] = now()->toIso8601String();
        $this->cache()->put($this->key($job['id']), $job, now()->addHours(self::TTL_HOURS));
    }

    private function push(string $jobId, string $action): void
    {
        $cache = $this->cache();
        $cache->lock('research-state-lock', 10)->block(2, function () use ($cache, $jobId, $action): void {
            $pending = $cache->get('research-pending', []);
            $pending[] = ['id' => $jobId, 'action' => $action];
            $cache->forever('research-pending', $pending);
        });
    }

    private function key(string $jobId): string
    {
        return 'research-job:'.$jobId;
    }
}
