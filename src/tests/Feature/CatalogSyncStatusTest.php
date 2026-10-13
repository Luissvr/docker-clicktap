<?php

namespace Tests\Feature;

use App\Livewire\CatalogSyncStatus;
use App\Models\User;
use App\Support\CatalogSyncState;
use App\Support\LocalCatalogSync;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogSyncStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_active_user_can_request_sync_without_running_it_in_the_form_request(): void
    {
        $this->actingAs(User::factory()->create());
        config(['clicktap.local_catalog' => true]);
        $this->mock(LocalCatalogSync::class)->shouldNotReceive('sync');
        Livewire::test(CatalogSyncStatus::class)->call('synchronize')->assertSee('Sincronización solicitada');
        $this->assertTrue(app(CatalogSyncState::class)->requested());
    }

    public function test_guest_and_inactive_users_cannot_request_sync(): void
    {
        Livewire::test(CatalogSyncStatus::class)->assertForbidden();
        $this->actingAs(User::factory()->create(['activo' => false]));
        Livewire::test(CatalogSyncStatus::class)->assertForbidden();
        $this->assertFalse(app(CatalogSyncState::class)->requested());
    }

    public function test_worker_syncs_on_startup_and_consumes_manual_request(): void
    {
        app(CatalogSyncState::class)->request();
        $this->mock(LocalCatalogSync::class)->shouldReceive('sync')->once()->andReturn([]);
        $this->artisan('clicktap:sync-worker', ['--once' => true])->assertSuccessful();
        $this->assertFalse(app(CatalogSyncState::class)->requested());
        $this->assertTrue(app(CatalogSyncState::class)->cache()->has('catalog-sync-worker-heartbeat'));
    }

    public function test_successful_sync_announces_completion_to_lists(): void
    {
        $this->actingAs(User::factory()->create());
        config(['clicktap.local_catalog' => true]);
        $component = Livewire::test(CatalogSyncStatus::class)->call('synchronize');
        $state = app(CatalogSyncState::class);
        $state->cache()->forget('catalog-sync-requested');
        $state->update(['completed_at' => now()->timestamp]);
        $component->call('refreshStatus')->assertDispatched('catalog-synced')->assertSee('Última sincronización');
    }
}
