<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;

class CatalogSyncState
{
    public function cache(): Repository
    {
        return cache()->store(config('clicktap.sync_cache_store', 'file'));
    }

    public function request(): void
    {
        $this->cache()->put('catalog-sync-requested', true, 3600);
    }

    public function requested(): bool
    {
        return (bool) $this->cache()->get('catalog-sync-requested', false);
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        return $this->cache()->get('catalog-sync-status', []);
    }

    public function update(array $changes): void
    {
        $this->cache()->forever('catalog-sync-status', array_merge($this->status(), $changes));
    }
}
