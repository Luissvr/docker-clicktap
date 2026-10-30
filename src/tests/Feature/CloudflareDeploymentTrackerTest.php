<?php

namespace Tests\Feature;

use App\Models\Pagina;
use App\Support\CloudflareDeploymentTracker;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CloudflareDeploymentTrackerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.cloudflare.account_id' => 'account',
            'services.cloudflare.pages_project' => 'site',
            'services.cloudflare.token' => 'test-token',
            'services.cloudflare.public_url' => 'https://site.example',
        ]);
    }

    public function test_matching_successful_production_deployment_and_live_page_confirm_publication(): void
    {
        $page = Pagina::factory()->create(['slug' => 'demo', 'publicacion_commit' => 'expected-sha', 'publicacion_estado' => 'enviada']);
        $this->fakeDeployment('expected-sha', 200);
        $tracker = app(CloudflareDeploymentTracker::class);
        $tracker->track($page, 'expected-sha', 'publish');

        $this->travel(6)->seconds();
        $tracker->check();

        $this->assertSame('publicada', $page->fresh()->publicacion_estado);
        $this->assertNull($tracker->pageStatus($page->id));
        $this->assertSame('publicada', cache()->store('array')->get('publication-result:'.$page->id)['state']);
        $this->assertSame('cloudflare_check', cache()->store('array')->get('publication-result:'.$page->id)['events'][0]['stage']);
        $this->assertSame('publicada', cache()->store('array')->get('publication-result:'.$page->id)['events'][1]['stage']);
    }

    public function test_withdrawal_remains_unconfirmed_while_public_route_still_responds(): void
    {
        $page = Pagina::factory()->create(['slug' => 'retired', 'activo' => false, 'publicacion_commit' => 'withdraw-sha', 'publicacion_estado' => 'retirada_enviada']);
        $this->fakeDeployment('withdraw-sha', 200);
        $tracker = app(CloudflareDeploymentTracker::class);
        $tracker->track($page, 'withdraw-sha', 'withdraw');

        $this->travel(6)->seconds();
        $tracker->check();

        $this->assertSame('retirada_enviada', $page->fresh()->publicacion_estado);
        $this->assertSame('comprobando_cloudflare', $tracker->pageStatus($page->id)['state']);
        $this->assertSame(1, $tracker->current()['attempts']);
        $this->assertNotNull($tracker->current()['last_checked_at']);
        $this->assertNull(cache()->store('array')->get('publication-result:'.$page->id));
    }

    public function test_withdrawal_is_confirmed_only_when_the_public_route_is_gone(): void
    {
        $page = Pagina::factory()->create(['slug' => 'retired', 'activo' => false, 'publicacion_commit' => 'withdraw-sha', 'publicacion_estado' => 'retirada_enviada']);
        $this->fakeDeployment('withdraw-sha', 404);
        $tracker = app(CloudflareDeploymentTracker::class);
        $tracker->track($page, 'withdraw-sha', 'withdraw');

        $this->travel(6)->seconds();
        $tracker->check();

        $this->assertSame('retirada', $page->fresh()->publicacion_estado);
        $this->assertNull($tracker->pageStatus($page->id));
        $this->assertSame('retirada', cache()->store('array')->get('publication-result:'.$page->id)['state']);
    }

    public function test_a_still_live_alias_prevents_confirming_withdrawal(): void
    {
        $page = Pagina::factory()->create(['slug' => 'retired', 'activo' => false, 'publicacion_commit' => 'withdraw-sha', 'publicacion_estado' => 'retirada_enviada']);
        DB::table('alias_pagina')->insert([
            'id' => (string) Str::uuid(), 'pagina_id' => $page->id, 'ruta' => 'old-route',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake(fn (Request $request) => str_contains($request->url(), 'api.cloudflare.com')
            ? Http::response(['result' => [[
                'environment' => 'production',
                'deployment_trigger' => ['metadata' => ['commit_hash' => 'withdraw-sha']],
                'latest_stage' => ['status' => 'success'],
            ]]])
            : Http::response('', str_ends_with($request->url(), '/old-route') ? 200 : 404));
        $tracker = app(CloudflareDeploymentTracker::class);
        $tracker->track($page, 'withdraw-sha', 'withdraw');

        $this->travel(6)->seconds();
        $tracker->check();

        $this->assertSame('retirada_enviada', $page->fresh()->publicacion_estado);
        $this->assertSame('comprobando_cloudflare', $tracker->pageStatus($page->id)['state']);
    }

    private function fakeDeployment(string $commit, int $publicStatus): void
    {
        Http::fake(fn (Request $request) => str_contains($request->url(), 'api.cloudflare.com')
            ? Http::response(['result' => [[
                'environment' => 'production',
                'deployment_trigger' => ['metadata' => ['commit_hash' => $commit]],
                'latest_stage' => ['status' => 'success'],
            ]]])
            : Http::response('public route', $publicStatus));

    }
}
