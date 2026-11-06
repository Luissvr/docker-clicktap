<?php

namespace Tests\Feature;

use App\Actions\RejectSaleAction;
use App\Filament\Pages\PublicacionesSitio;
use App\Livewire\PublicationActivity;
use App\Livewire\ConnectionStatus;
use App\Models\AlbumPagina;
use App\Models\Pagina;
use App\Models\User;
use App\Support\BusinessLedger;
use App\Support\PageSaleSnapshot;
use App\Support\PageTrash;
use App\Support\PublicationHistory;
use App\Support\PublicationQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class PublicacionesSitioTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_filters_use_the_local_commercial_projection_and_closed_activity_skips_history(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        Pagina::factory()->create(['nombre_publico' => 'Página rápida']);
        $saleReads = 0;
        DB::connection()->listen(function ($query) use (&$saleReads): void {
            if (str_contains($query->sql, 'from "ventas"')) {
                $saleReads++;
            }
        });
        $history = \Mockery::mock(new PublicationHistory);
        $history->shouldNotReceive('recent');
        $this->app->instance(PublicationHistory::class, $history);

        Livewire::test(PublicacionesSitio::class)
            ->assertSee('Página rápida')
            ->call('toggleFilter', 'for_sale')
            ->call('toggleFilter', 'for_sale')
            ->set('search', 'rápida')
            ->assertSee('Página rápida');

        $this->assertSame(0, $saleReads);
    }

    public function test_commercial_snapshot_survives_file_cache_deserialization(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $page = Pagina::factory()->create();
        $saleId = (string) Str::uuid();
        DB::table('ventas')->insert([
            'id' => $saleId, 'estado' => 'borrador', 'pagina_id' => $page->id,
            'registrado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $cachePath = storage_path('framework/cache/page-sale-snapshot-test-'.Str::uuid());
        config([
            'cache.stores.page_sale_snapshot_test' => [
                'driver' => 'file', 'path' => $cachePath, 'lock_path' => $cachePath,
            ],
            'clicktap.sync_cache_store' => 'page_sale_snapshot_test',
        ]);

        try {
            $first = app(PageSaleSnapshot::class)->all();
            $second = app(PageSaleSnapshot::class)->all();

            $this->assertSame($saleId, $first->first()->id);
            $this->assertSame($page->id, $second->keyBy('pagina_id')->get($page->id)->pagina_id);
        } finally {
            File::deleteDirectory($cachePath);
        }
    }

    public function test_page_performance_trace_records_counts_without_query_text(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        config(['clicktap.performance_logging' => true]);
        Log::spy();

        $response = $this->get(PublicacionesSitio::getUrl());

        $response->assertOk()->assertHeader('X-Clicktap-Trace-Id');
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'clicktap.panel_performance'
                && $context['route'] === 'admin/publicaciones-sitio'
                && $context['interaction'] === 'initial'
                && $context['db_queries'] > 0
                && $context['response_bytes'] > 0
                && ! array_key_exists('sql', $context);
        })->once();
    }

    public function test_rejecting_a_sale_invalidates_the_page_filter_snapshot(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        $page = Pagina::factory()->create(['nombre_publico' => 'Página en negociación']);
        $saleId = (string) Str::uuid();
        DB::table('ventas')->insert([
            'id' => $saleId, 'estado' => 'borrador', 'pagina_id' => $page->id,
            'registrado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $component = Livewire::test(PublicacionesSitio::class)
            ->set('filter', 'for_sale')
            ->assertSee('Página en negociación');

        app(RejectSaleAction::class)->handle($actor, $saleId, 'El cliente no continúa');

        $component->set('filter', 'rejected')
            ->assertSee('Página en negociación');
    }

    public function test_page_without_sale_can_be_rejected_once_with_an_audited_reason(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        $page = Pagina::factory()->create(['nombre_publico' => 'Página sin venta', 'publicacion_estado' => 'publicada']);

        Livewire::test(PublicacionesSitio::class)
            ->set('withdrawalReasons.'.$page->id, 'El cliente canceló el proyecto')
            ->call('withdrawPage', $page->id)
            ->assertHasNoErrors()
            ->call('withdrawPage', $page->id)
            ->assertStatus(409);

        $this->assertDatabaseHas('paginas', [
            'id' => $page->id, 'estado_comercial' => 'rechazada',
            'activo' => false, 'publicacion_estado' => 'retirada_pendiente',
        ]);
        $this->assertDatabaseHas('eventos_auditoria', [
            'entidad_tipo' => 'pagina', 'entidad_id' => $page->id,
            'accion' => 'pagina_rechazada', 'motivo' => 'El cliente canceló el proyecto',
        ]);
        $this->assertSame(1, app(PublicationQueue::class)->status()['pendingCount']);
    }

    public function test_mark_sold_starts_one_linked_sale_for_a_page(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $page = Pagina::factory()->create();

        Livewire::test(PublicacionesSitio::class)
            ->assertSee('Marcar vendida')
            ->call('startSaleForPage', $page->id)
            ->assertRedirect();
        Livewire::test(PublicacionesSitio::class)
            ->call('startSaleForPage', $page->id)
            ->assertRedirect();

        $this->assertSame(1, DB::table('ventas')->where('pagina_id', $page->id)->count());
        $this->assertSame('por_vender', $page->fresh()->estado_comercial);
        $this->assertSame(DB::table('ventas')->where('pagina_id', $page->id)->value('id'), $page->fresh()->venta_id);
    }

    public function test_remote_sale_outage_does_not_block_listing_with_local_projection(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        Pagina::factory()->create(['nombre_publico' => 'Página disponible']);
        app(PageSaleSnapshot::class)->all();
        app(PageSaleSnapshot::class)->invalidate();
        $ledger = $this->mock(BusinessLedger::class);
        $ledger->shouldReceive('connection')->andThrow(new RuntimeException('Supabase no disponible'));

        Livewire::test(PublicacionesSitio::class)
            ->assertSee('Página disponible')
            ->assertDontSee('Se muestran los últimos estados comerciales conocidos');
    }

    public function test_cards_separate_sold_for_sale_and_rejected_pages(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        $sold = Pagina::factory()->create(['nombre_publico' => 'Página vendida', 'estado_comercial' => 'vendida', 'publicacion_estado' => 'publicada']);
        Pagina::factory()->create(['nombre_publico' => 'Página por vender', 'publicacion_estado' => 'sin_publicar']);
        $rejected = Pagina::factory()->create(['nombre_publico' => 'Página rechazada', 'estado_comercial' => 'rechazada', 'activo' => false, 'publicacion_estado' => 'retirada']);
        DB::table('ventas')->insert([
            'id' => (string) Str::uuid(), 'estado' => 'rechazada', 'pagina_id' => $rejected->id,
            'registrado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ventas')->insert([
            'id' => (string) Str::uuid(), 'estado' => 'confirmada', 'pagina_id' => $sold->id,
            'registrado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::test(PublicacionesSitio::class)
            ->set('filter', 'sold')->assertSee('Página vendida')->assertDontSee('Página por vender')->assertDontSee('Página rechazada')
            ->set('filter', 'for_sale')->assertSee('Página por vender')->assertDontSee('Página vendida')->assertDontSee('Página rechazada')
            ->set('filter', 'rejected')->assertSee('Página rechazada')->assertSee('Rechazada')->assertDontSee('Página vendida');
    }

    public function test_search_and_status_cards_replace_the_old_filters_and_can_show_all_pages_again(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        $sold = Pagina::factory()->create(['nombre_publico' => 'Página vendida', 'estado_comercial' => 'vendida', 'publicacion_estado' => 'publicada']);
        Pagina::factory()->create(['nombre_publico' => 'Página por vender', 'publicacion_estado' => 'sin_publicar']);
        DB::table('ventas')->insert([
            'id' => (string) Str::uuid(), 'estado' => 'confirmada', 'pagina_id' => $sold->id,
            'registrado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::test(PublicacionesSitio::class)
            ->assertSee('Buscar página o local')
            ->assertDontSee('Páginas y acceso público')
            ->assertDontSee('publication-filter')
            ->call('toggleFilter', 'sold')
            ->assertSet('filter', 'sold')
            ->assertSee('Página vendida')
            ->assertDontSee('Página por vender')
            ->call('toggleFilter', 'sold')
            ->assertSet('filter', 'all')
            ->assertSee('Página por vender');
    }

    public function test_sort_and_page_size_are_applied_before_rendering_cards(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        Pagina::factory()->create(['nombre_publico' => 'Alfa']);
        Pagina::factory()->create(['nombre_publico' => 'Zeta']);
        Pagina::factory()->create(['nombre_publico' => 'Mora']);

        Livewire::test(PublicacionesSitio::class)
            ->assertSee('Ordenar por')
            ->assertSee('Mostrar páginas')
            ->set('sort', 'name_asc')
            ->set('perPage', 10)
            ->assertSeeInOrder(['Alfa', 'Mora', 'Zeta'])
            ->set('sort', 'name_desc')
            ->assertSeeInOrder(['Zeta', 'Mora', 'Alfa']);
    }

    public function test_commercial_counts_follow_search_and_album(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $matching = Pagina::factory()->create(['nombre_publico' => 'Ruta centro']);
        Pagina::factory()->create(['nombre_publico' => 'Ruta norte']);
        $album = AlbumPagina::create(['nombre' => 'Centro']);
        $album->paginas()->attach($matching->id);

        $component = Livewire::test(PublicacionesSitio::class)
            ->set('albumFilter', $album->id)
            ->set('search', 'centro');

        $this->assertSame(1, $component->instance()->getViewData()['counts']['for_sale']);
        $component->set('search', 'norte');
        $this->assertSame(0, $component->instance()->getViewData()['counts']['for_sale']);
    }

    public function test_connection_button_shows_the_queue_and_worker_signal(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        config(['clicktap.publication_slugs' => null]);
        $page = Pagina::factory()->create(['nombre_publico' => 'Página en cola']);
        app(PublicationQueue::class)->request($page);

        Livewire::test(ConnectionStatus::class)
            ->assertSee('Conexión')
            ->assertSee('1 en espera')
            ->assertSee('Publicador sin señal')
            ->assertSet('activityLoaded', false);
    }

    public function test_selected_drafts_are_activated_and_queued_without_changing_published_pages(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        config(['clicktap.publication_slugs' => null]);
        $inactive = Pagina::factory()->create(['nombre_publico' => 'Borrador inactivo', 'activo' => false, 'publicacion_estado' => 'sin_publicar']);
        $active = Pagina::factory()->create(['nombre_publico' => 'Borrador activo', 'activo' => true, 'publicacion_estado' => 'sin_publicar']);
        $published = Pagina::factory()->create([
            'nombre_publico' => 'Página publicada', 'activo' => true,
            'publicacion_estado' => 'publicada', 'publicacion_commit' => 'abc', 'publicada_en' => now(),
        ]);

        Livewire::test(PublicacionesSitio::class)
            ->set('selectedPages', [$inactive->id, $active->id])
            ->assertDontSee('Publicar seleccionadas')
            ->call('publishSelectedDrafts')
            ->assertHasNoErrors()
            ->assertSet('selectedPages', []);

        $this->assertTrue($inactive->fresh()->activo);
        $this->assertTrue($active->fresh()->activo);
        $this->assertSame('publicada', $published->fresh()->publicacion_estado);
        $this->assertSame('abc', $published->fresh()->publicacion_commit);
        $this->assertSame(2, app(PublicationQueue::class)->status()['pendingCount']);
        $this->assertSame('pendiente', app(PublicationQueue::class)->pageStatus($inactive->id)['state']);
        $this->assertSame('pendiente', app(PublicationQueue::class)->pageStatus($active->id)['state']);
        $this->assertNull(app(PublicationQueue::class)->pageStatus($published->id));
    }

    public function test_bulk_publication_rejects_a_published_page_before_activating_drafts(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        config(['clicktap.publication_slugs' => null]);
        $draft = Pagina::factory()->create(['activo' => false, 'publicacion_estado' => 'sin_publicar']);
        $published = Pagina::factory()->create([
            'publicacion_estado' => 'publicada', 'publicacion_commit' => 'abc', 'publicada_en' => now(),
        ]);

        Livewire::test(PublicacionesSitio::class)
            ->set('selectedPages', [$draft->id, $published->id])
            ->call('publishSelectedDrafts')
            ->assertStatus(409);

        $this->assertFalse($draft->fresh()->activo);
        $this->assertSame('publicada', $published->fresh()->publicacion_estado);
        $this->assertSame(0, app(PublicationQueue::class)->status()['pendingCount']);
    }

    public function test_bulk_publication_rejects_a_page_with_a_rejected_sale(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        config(['clicktap.publication_slugs' => null]);
        $draft = Pagina::factory()->create(['activo' => false, 'publicacion_estado' => 'sin_publicar']);
        DB::table('ventas')->insert([
            'id' => (string) Str::uuid(), 'estado' => 'rechazada', 'pagina_id' => $draft->id,
            'registrado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::test(PublicacionesSitio::class)
            ->set('selectedPages', [$draft->id])
            ->call('publishSelectedDrafts')
            ->assertStatus(409);

        $this->assertFalse($draft->fresh()->activo);
        $this->assertSame(0, app(PublicationQueue::class)->status()['pendingCount']);
    }

    public function test_bulk_publication_allows_new_drafts_outside_the_legacy_slug_selection(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $draft = Pagina::factory()->create(['slug' => 'fuera-de-seleccion', 'activo' => false, 'publicacion_estado' => 'sin_publicar']);
        config(['clicktap.publication_slugs' => ['otra-pagina']]);

        Livewire::test(PublicacionesSitio::class)
            ->set('selectedPages', [$draft->id])
            ->call('publishSelectedDrafts')
            ->assertHasNoErrors();

        $this->assertTrue($draft->fresh()->activo);
        $this->assertSame(1, app(PublicationQueue::class)->status()['pendingCount']);
    }

    public function test_sold_page_cannot_be_unpublished_from_the_commercial_card(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        $page = Pagina::factory()->create(['estado_comercial' => 'vendida', 'publicacion_estado' => 'publicada', 'publicacion_commit' => 'abc', 'publicada_en' => now()]);
        $saleId = (string) Str::uuid();
        DB::table('ventas')->insert([
            'id' => $saleId, 'estado' => 'confirmada', 'pagina_id' => $page->id,
            'registrado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::test(PublicacionesSitio::class)->assertDontSee('Retirar del sitio')->call('unpublishPage', $page->id)->assertStatus(410);

        $this->assertDatabaseHas('ventas', ['id' => $saleId, 'estado' => 'confirmada']);
        $this->assertDatabaseHas('paginas', ['id' => $page->id, 'activo' => true, 'publicacion_estado' => 'publicada']);
        $this->assertSame(0, app(PublicationQueue::class)->status()['pendingCount']);
    }

    public function test_rejected_sale_cannot_be_reactivated_by_editing_the_page(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        $page = Pagina::factory()->create(['activo' => false]);
        DB::table('ventas')->insert([
            'id' => (string) Str::uuid(), 'estado' => 'rechazada', 'pagina_id' => $page->id,
            'registrado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Resuelve la venta rechazada');
        $page->forceFill(['activo' => true])->save();
    }

    public function test_cards_remain_available_while_the_trash_migration_is_pending(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        Pagina::factory()->create(['nombre_publico' => 'Página existente']);
        Schema::shouldReceive('hasColumn')->with('paginas', 'papelera_en')->andReturn(false);
        $this->assertFalse(app(PageTrash::class)->available());
        $this->assertSame(0, Artisan::call('clicktap:prune-page-trash'));

        Livewire::test(PublicacionesSitio::class)
            ->assertSee('Página existente')
            ->assertSee('La papelera estará disponible después de actualizar el catálogo.');
    }

    public function test_route_album_groups_selected_pages_without_changing_publication(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $page = Pagina::factory()->create(['nombre_publico' => 'Panini Point', 'publicacion_estado' => 'publicada']);
        $other = Pagina::factory()->create(['nombre_publico' => 'Otra página']);

        $component = Livewire::test(PublicacionesSitio::class)
            ->set('albumName', 'Ruta centro')
            ->set('albumDate', '2026-11-05')
            ->call('createAlbum')
            ->assertHasNoErrors();

        $album = AlbumPagina::query()->firstOrFail();
        $component->call('startEditingAlbum')
            ->assertSet('editingAlbumId', $album->id)
            ->set('editingAlbumName', 'Ruta renovada')
            ->set('editingAlbumSector', 'Centro')
            ->call('saveAlbum')
            ->assertHasNoErrors()
            ->assertSee('Ruta renovada');
        $this->assertDatabaseHas('albumes_paginas', ['id' => $album->id, 'nombre' => 'Ruta renovada', 'sector' => 'Centro']);
        $component->set('selectedPages', [$page->id])
            ->set('targetAlbum', $album->id)
            ->call('addSelectedToAlbum')
            ->assertHasNoErrors()
            ->set('albumFilter', $album->id)
            ->assertSee('Panini Point')
            ->assertDontSee('Otra página');

        $this->assertDatabaseHas('album_pagina', ['album_id' => $album->id, 'pagina_id' => $page->id]);
        $this->assertSame('publicada', $page->fresh()->publicacion_estado);
        $this->assertDatabaseHas('paginas', ['id' => $other->id]);

        $component->call('removePageFromAlbum', $page->id)->assertHasNoErrors()->assertDontSee('Panini Point');
        $this->assertDatabaseMissing('album_pagina', ['album_id' => $album->id, 'pagina_id' => $page->id]);
        $component->call('deleteAlbum')->assertHasNoErrors();
        $this->assertDatabaseHas('paginas', ['id' => $page->id]);
    }

    public function test_connection_panel_shows_previous_attempts_for_a_page(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $page = Pagina::factory()->create();
        app(PublicationHistory::class)->record([
            'id' => (string) Str::uuid(), 'page_id' => $page->id, 'name' => $page->nombre_publico,
            'type' => 'publish', 'state' => 'fallida', 'requested_at' => now()->toIso8601String(),
            'message' => 'Falló el build de Astro', 'events' => [],
        ]);

        Livewire::test(ConnectionStatus::class)
            ->assertDontSee('Falló el build de Astro')
            ->call('showActivity', $page->id)
            ->assertSet('activityLoaded', true)
            ->assertSee('Falló el build de Astro');
    }

    public function test_activity_connection_map_uses_configured_service_links_only(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        config([
            'services.supabase.dashboard_url' => 'https://supabase.com/dashboard/project/example',
            'services.cloudflare.dashboard_url' => 'https://dash.cloudflare.com/example/pages/view/clicktap',
        ]);

        Livewire::test(PublicationActivity::class)
            ->assertSee('Comunicación entre servicios')
            ->assertSee('https://supabase.com/dashboard/project/example')
            ->assertSee('https://dash.cloudflare.com/example/pages/view/clicktap');

        config(['services.supabase.dashboard_url' => 'https://example.com/not-supabase']);
        Livewire::test(PublicationActivity::class)->assertDontSee('https://example.com/not-supabase');
    }

    public function test_selection_is_kept_in_the_browser_so_fast_clicks_do_not_trigger_server_renders(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        Pagina::factory()->create(['nombre_publico' => 'Página seleccionable']);

        $html = Livewire::test(PublicacionesSitio::class)->html();

        $this->assertStringContainsString('wire:model="selectedPages"', $html);
        $this->assertStringNotContainsString('wire:model.live="selectedPages"', $html);
        $this->assertStringContainsString('data-select-page', $html);
        $this->assertStringContainsString('data-rejectable="1"', $html);
    }

    public function test_selected_pages_are_rejected_together_with_one_audited_reason(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        $first = Pagina::factory()->create(['nombre_publico' => 'Primera', 'publicacion_estado' => 'publicada']);
        $second = Pagina::factory()->create(['nombre_publico' => 'Segunda', 'publicacion_estado' => 'sin_publicar']);
        $untouched = Pagina::factory()->create(['nombre_publico' => 'Sin tocar']);

        Livewire::test(PublicacionesSitio::class)
            ->set('selectedPages', [$first->id, $second->id])
            ->set('bulkRejectReason', 'Ruta cerrada por el cliente')
            ->call('rejectSelectedPages')
            ->assertHasNoErrors()
            ->assertSet('selectedPages', [])
            ->assertSet('bulkRejectReason', '');

        foreach ([$first, $second] as $page) {
            $this->assertDatabaseHas('paginas', [
                'id' => $page->id, 'estado_comercial' => 'rechazada',
                'activo' => false, 'publicacion_estado' => 'retirada_pendiente',
            ]);
            $this->assertDatabaseHas('eventos_auditoria', [
                'entidad_id' => $page->id, 'accion' => 'pagina_rechazada', 'motivo' => 'Ruta cerrada por el cliente',
            ]);
        }
        $this->assertSame('por_vender', $untouched->fresh()->estado_comercial);
        $this->assertSame(2, app(PublicationQueue::class)->status()['pendingCount']);
    }

    public function test_bulk_rejection_skips_sold_and_trashed_pages_and_keeps_the_rest(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $forSale = Pagina::factory()->create(['nombre_publico' => 'Por vender']);
        $sold = Pagina::factory()->create(['nombre_publico' => 'Ya vendida', 'estado_comercial' => 'vendida']);
        $trashed = Pagina::factory()->create(['nombre_publico' => 'En papelera', 'papelera_en' => now()]);

        Livewire::test(PublicacionesSitio::class)
            ->set('selectedPages', [$forSale->id, $sold->id, $trashed->id])
            ->set('bulkRejectReason', 'Cliente no continúa')
            ->call('rejectSelectedPages')
            ->assertHasNoErrors();

        $this->assertSame('rechazada', $forSale->fresh()->estado_comercial);
        $this->assertSame('vendida', $sold->fresh()->estado_comercial);
        $this->assertSame('por_vender', $trashed->fresh()->estado_comercial);
        $this->assertSame(1, app(PublicationQueue::class)->status()['pendingCount']);
    }

    public function test_bulk_rejection_requires_a_reason_and_limits_the_batch(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $page = Pagina::factory()->create();
        $many = Pagina::factory()->count(26)->create()->pluck('id')->all();

        Livewire::test(PublicacionesSitio::class)
            ->set('selectedPages', [$page->id])
            ->set('bulkRejectReason', 'ab')
            ->call('rejectSelectedPages')
            ->assertHasErrors('bulkRejectReason')
            ->set('selectedPages', $many)
            ->set('bulkRejectReason', 'Motivo válido')
            ->call('rejectSelectedPages')
            ->assertHasErrors('bulkRejectReason');

        $this->assertSame('por_vender', $page->fresh()->estado_comercial);
        $this->assertSame(0, Pagina::query()->where('estado_comercial', 'rechazada')->count());
    }

    public function test_bulk_rejection_with_a_draft_sale_uses_the_sale_rejection_action(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        $page = Pagina::factory()->create(['nombre_publico' => 'Con venta']);
        DB::table('ventas')->insert([
            'id' => $saleId = (string) Str::uuid(), 'estado' => 'borrador', 'pagina_id' => $page->id,
            'registrado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $page->forceFill(['venta_id' => $saleId])->save();

        Livewire::test(PublicacionesSitio::class)
            ->set('selectedPages', [$page->id])
            ->set('bulkRejectReason', 'No concretada')
            ->call('rejectSelectedPages')
            ->assertHasNoErrors();

        $this->assertSame('rechazada', DB::table('ventas')->where('id', $saleId)->value('estado'));
        $this->assertSame('rechazada', $page->fresh()->estado_comercial);
    }

    public function test_connection_button_level_follows_worker_queue_and_failures(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        config(['clicktap.publication_slugs' => null]);
        $component = Livewire::test(ConnectionStatus::class)->assertSee('Requiere revisión');

        cache()->store(config('clicktap.publication_store'))->put('publication-worker-heartbeat', now()->toIso8601String(), 180);
        Livewire::test(ConnectionStatus::class)->assertSee('Conectado');

        Pagina::factory()->create(['publicacion_estado' => 'fallida']);
        Livewire::test(ConnectionStatus::class)->assertSee('Requiere revisión')->assertSee('1 página con envío fallido');
    }

    public function test_connection_button_announces_status_changes_to_the_page_list(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        config(['clicktap.publication_slugs' => null]);
        cache()->store(config('clicktap.publication_store'))->put('publication-worker-heartbeat', now()->toIso8601String(), 180);
        $component = Livewire::test(ConnectionStatus::class)->call('refreshStatus')->assertNotDispatched('publication-status-changed');

        app(PublicationQueue::class)->request(Pagina::factory()->create());
        $component->call('refreshStatus')->assertDispatched('publication-status-changed');
    }

    public function test_page_view_no_longer_embeds_activity_or_connection_blocks_and_has_the_new_layout(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        Pagina::factory()->create(['nombre_publico' => 'Página visible']);

        $html = Livewire::test(PublicacionesSitio::class)->html();

        $this->assertStringNotContainsString('Publicador conectado', $html);
        $this->assertStringNotContainsString('ct-activity-backdrop', $html);
        $this->assertStringContainsString('Crear página', $html);
        $this->assertStringContainsString('Crear álbum', $html);
        $this->assertLessThan(strpos($html, 'Crear página'), strpos($html, 'Vendidas'));
        $this->assertLessThan(strpos($html, 'Buscar página o local'), strpos($html, 'Crear álbum'));
    }

    public function test_clear_filters_restores_defaults(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));

        Livewire::test(PublicacionesSitio::class)
            ->set('search', 'x')->set('perPage', 50)->set('sort', 'name_asc')->set('filter', 'sold')
            ->call('clearFilters')
            ->assertSet('search', '')->assertSet('perPage', 25)->assertSet('sort', 'recently_updated')->assertSet('filter', 'all');
    }
}
