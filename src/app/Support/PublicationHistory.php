<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;

final class PublicationHistory
{
    private function cache(): Repository
    {
        return cache()->store(config('clicktap.publication_store', 'publications'));
    }

    public function record(array $operation): void
    {
        $cache = $this->cache();
        $cache->lock('publication-history-lock', 10)->block(2, function () use ($cache, $operation): void {
            $history = $cache->get('publication-history', []);
            $history = array_values(array_filter($history, fn (array $item): bool => ($item['id'] ?? null) !== ($operation['id'] ?? null)));
            array_unshift($history, $operation);
            $cache->forever('publication-history', array_slice($history, 0, 200));
        });
    }

    public function recent(?string $pageId = null): array
    {
        $history = $this->cache()->get('publication-history', []);

        return $pageId === null ? $history : array_values(array_filter(
            $history,
            fn (array $operation): bool => ($operation['page_id'] ?? null) === $pageId,
        ));
    }
}
