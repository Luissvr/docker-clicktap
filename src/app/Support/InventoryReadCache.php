<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;

class InventoryReadCache
{
    public function remember(Connection $connection, string $key, Closure $read): array
    {
        if ($connection->transactionLevel() > 0) {
            return $read();
        }

        $cache = app(CatalogSyncState::class)->cache();
        $scope = $this->scope($connection);
        $version = $cache->get($scope.':version', 'initial');

        return $cache->remember($scope.':'.$version.':'.hash('sha256', auth()->id().'|'.$key), 20, $read);
    }

    public function invalidate(Connection $connection): void
    {
        $invalidate = fn () => app(CatalogSyncState::class)->cache()
            ->put($this->scope($connection).':version', (string) Str::uuid(), 86400);

        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit($invalidate);
        } else {
            $invalidate();
        }
    }

    private function scope(Connection $connection): string
    {
        return 'inventory-read:'.hash('sha256', json_encode([
            $connection->getName(), $connection->getConfig('host'),
            $connection->getDatabaseName(), $connection->getConfig('search_path'),
        ]));
    }
}
