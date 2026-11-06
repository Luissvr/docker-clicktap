<?php

namespace App\Livewire;

use App\Models\Pagina;
use App\Support\CatalogConnection;
use App\Support\CatalogSyncState;
use App\Support\PageTrash;
use App\Support\PublicationQueue;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Botón "Conexión" del encabezado: resume el estado del publicador y de la
 * sincronización con Supabase, y abre el panel con la actividad de despliegues.
 */
class ConnectionStatus extends Component
{
    #[Locked]
    public ?int $observedCompletion = null;

    #[Locked]
    public string $signature = '';

    public bool $activityLoaded = false;

    #[Locked]
    public ?string $activityPageId = null;

    public function mount(): void
    {
        $snapshot = $this->snapshot();
        $this->observedCompletion = $snapshot['sync']['completed_at'] ?? null;
        $this->signature = $snapshot['signature'];
    }

    public function showActivity(?string $pageId = null): void
    {
        abort_unless(auth()->user()?->activo, 403);
        $this->activityPageId = $pageId;
        $this->activityLoaded = true;
    }

    public function synchronize(): void
    {
        abort_unless(CatalogConnection::enabled() && auth()->user()?->activo, 403);
        $this->observedCompletion = app(CatalogSyncState::class)->status()['completed_at'] ?? null;
        app(CatalogSyncState::class)->request();
    }

    public function refreshStatus(): void
    {
        abort_unless(auth()->user()?->activo, 403);
        $snapshot = $this->snapshot();

        $completed = $snapshot['sync']['completed_at'] ?? null;
        if ($completed !== null && $completed !== $this->observedCompletion) {
            $this->observedCompletion = $completed;
            $this->dispatch('catalog-synced');
        }

        if ($snapshot['signature'] !== $this->signature) {
            $this->signature = $snapshot['signature'];
            $this->dispatch('publication-status-changed');
        }
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->activo, 403);

        return view('livewire.connection-status', ['snapshot' => $this->snapshot()]);
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $queue = app(PublicationQueue::class)->status();
        $state = app(CatalogSyncState::class);
        $sync = CatalogConnection::enabled() ? $state->status() : null;
        $syncRunning = $sync !== null && ($sync['running_since'] ?? 0) > time() - 180;
        $syncBusy = $syncRunning || ($sync !== null && $state->requested());
        $syncError = $sync !== null && ! $syncBusy && ! empty($sync['error']);

        try {
            $failedQuery = Pagina::query()->whereIn('publicacion_estado', ['fallida', 'retirada_fallida']);
            if (app(PageTrash::class)->available()) {
                $failedQuery->whereNull('papelera_en');
            }
            $failedCount = $failedQuery->count();
        } catch (\Throwable $exception) {
            // El indicador no debe romper el encabezado si el catálogo no responde.
            report($exception);
            $failedCount = 0;
        }

        $publishBusy = $queue['pendingCount'] > 0 || ! empty($queue['running']) || $queue['tracking'];
        $level = match (true) {
            ! $queue['workerAvailable'] || $syncError || $failedCount > 0 => 'error',
            $publishBusy || $syncBusy => 'busy',
            default => 'ok',
        };

        return [
            'queue' => $queue,
            'sync' => $sync,
            'syncEnabled' => $sync !== null,
            'syncRunning' => $syncRunning,
            'syncBusy' => $syncBusy,
            'syncError' => $syncError,
            'failedCount' => $failedCount,
            'level' => $level,
            'label' => match ($level) {
                'error' => 'Requiere revisión',
                'busy' => 'En curso',
                default => 'Conectado',
            },
            'signature' => md5(json_encode([
                $queue['pendingCount'],
                $queue['running']['page_id'] ?? null,
                $queue['tracking'],
                $queue['result']['page_id'] ?? null,
                $queue['result']['state'] ?? null,
                $failedCount,
                $sync['completed_at'] ?? null,
            ])),
        ];
    }
}
