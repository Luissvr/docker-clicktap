<?php

namespace App\Livewire;

use App\Support\CatalogConnection;
use App\Support\CatalogSyncState;
use App\Support\PublicationHistory;
use App\Support\PublicationQueue;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PublicationActivity extends Component
{
    #[Locked]
    public ?string $pageId = null;

    public function render(): View
    {
        abort_unless(auth()->user()?->activo, 403);

        $activity = app(PublicationHistory::class)->recent($this->pageId);
        $status = app(PublicationQueue::class)->status();
        foreach (['running', 'pending', 'trackingTask'] as $key) {
            $operation = $status[$key] ?? null;
            if ($operation && ($this->pageId === null || ($operation['page_id'] ?? null) === $this->pageId)) {
                array_unshift($activity, $operation + ['state' => $key]);
            }
        }

        $sync = CatalogConnection::enabled() ? app(CatalogSyncState::class)->status() : [];
        $latest = $activity[0] ?? null;

        return view('livewire.publication-activity', [
            'activity' => $activity,
            'sync' => $sync,
            'publicationStatus' => $status,
            'latestOperation' => $latest,
            'supabaseUrl' => $this->serviceUrl(config('services.supabase.dashboard_url'), 'supabase.com'),
            'cloudflareUrl' => $this->serviceUrl(config('services.cloudflare.dashboard_url'), 'dash.cloudflare.com'),
        ]);
    }

    private function serviceUrl(mixed $value, string $host): ?string
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        return parse_url($value, PHP_URL_SCHEME) === 'https' && parse_url($value, PHP_URL_HOST) === $host
            ? $value
            : null;
    }
}
