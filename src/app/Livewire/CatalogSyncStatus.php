<?php

namespace App\Livewire;

use App\Support\CatalogConnection;
use App\Support\CatalogSyncState;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CatalogSyncStatus extends Component
{
    #[Locked]
    public ?int $observedCompletion = null;

    public function synchronize(): void
    {
        abort_unless(CatalogConnection::enabled() && auth()->user()?->activo, 403);
        $this->observedCompletion = app(CatalogSyncState::class)->status()['completed_at'] ?? null;
        app(CatalogSyncState::class)->request();
    }

    public function refreshStatus(): void
    {
        abort_unless(auth()->user()?->activo, 403);
        $completed = app(CatalogSyncState::class)->status()['completed_at'] ?? null;
        if ($completed !== null && $completed !== $this->observedCompletion) {
            $this->observedCompletion = $completed;
            $this->dispatch('catalog-synced');
        }
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->activo, 403);
        $state = app(CatalogSyncState::class);
        $status = $state->status();
        $running = ($status['running_since'] ?? 0) > time() - 180;

        return view('livewire.catalog-sync-status', [
            'status' => $status,
            'busy' => $running || $state->requested(),
            'running' => $running,
            'workerAvailable' => $running || $state->cache()->has('catalog-sync-worker-heartbeat'),
        ]);
    }
}
