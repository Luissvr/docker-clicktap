<?php

namespace App\Livewire;

use App\Support\PublicationQueue;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PublicationWorkerMonitor extends Component
{
    public function render(): View
    {
        abort_unless(auth()->user()?->activo, 403);

        return view('livewire.publication-worker-monitor', app(PublicationQueue::class)->status() + [
            'cloudflareConfigured' => filled(config('services.cloudflare.account_id'))
                && filled(config('services.cloudflare.pages_project'))
                && filled(config('services.cloudflare.token')),
        ]);
    }
}
