<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Throwable;

final class PageSaleSnapshot
{
    private const KEY = 'page-sale-snapshot:v2';

    private bool $stale = false;

    /** @return Collection<int, object> */
    public function all(): Collection
    {
        $cache = app(CatalogSyncState::class)->cache();
        $key = $this->key();
        $this->stale = false;
        $rows = $cache->get($key.':fresh');
        if (! is_array($rows)) {
            try {
                $rows = app(BusinessLedger::class)->connection()
                    ->table('ventas')
                    ->whereNotNull('pagina_id')
                    ->get(['id', 'pagina_id', 'estado'])
                    ->map(fn (object $sale): array => [
                        'id' => $sale->id,
                        'pagina_id' => $sale->pagina_id,
                        'estado' => $sale->estado,
                    ])
                    ->all();
                $cache->put($key.':fresh', $rows, 60);
                $cache->forever($key.':last-known', $rows);
            } catch (Throwable $exception) {
                $rows = $cache->get($key.':last-known');
                if (! is_array($rows)) {
                    throw $exception;
                }
                $this->stale = true;
                if ($cache->add($key.':outage-reported', true, 60)) {
                    report($exception);
                }
            }
        }

        return collect($rows)->map(fn (array $sale): object => (object) $sale);
    }

    public function isStale(): bool
    {
        return $this->stale;
    }

    public function invalidate(?Connection $connection = null): void
    {
        $key = $this->key();
        $forget = fn (): bool => app(CatalogSyncState::class)->cache()->forget($key.':fresh');
        if ($connection && $connection->transactionLevel() > 0) {
            $connection->afterCommit($forget);

            return;
        }

        $forget();
    }

    private function key(): string
    {
        $name = CatalogConnection::enabled() ? 'supabase' : config('database.default');

        return self::KEY.':'.hash('sha256', json_encode([
            $name,
            config("database.connections.{$name}.host"),
            config("database.connections.{$name}.database"),
            config("database.connections.{$name}.search_path"),
        ]));
    }
}
