<?php

namespace Tests\Feature;

use App\Filament\Pages\PublicacionesSitio;
use App\Livewire\PublicationWorkerMonitor;
use App\Models\Pagina;
use App\Models\User;
use App\Support\PageTrash;
use App\Support\PublicationQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class PublicacionesSitioTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cards_separate_published_for_sale_and_rejected_pages(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        Pagina::factory()->create(['nombre_publico' => 'Página publicada', 'publicacion_estado' => 'publicada']);
        Pagina::factory()->create(['nombre_publico' => 'Página por vender', 'publicacion_estado' => 'sin_publicar']);
        $rejected = Pagina::factory()->create(['nombre_publico' => 'Página rechazada', 'activo' => false, 'publicacion_estado' => 'retirada']);
        DB::table('ventas')->insert([
            'id' => (string) Str::uuid(), 'estado' => 'rechazada', 'pagina_id' => $rejected->id,
            'registrado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::test(PublicacionesSitio::class)
            ->set('filter', 'published')->assertSee('Página publicada')->assertDontSee('Página por vender')->assertDontSee('Página rechazada')
            ->set('filter', 'for_sale')->assertSee('Página por vender')->assertDontSee('Página publicada')->assertDontSee('Página rechazada')
            ->set('filter', 'rejected')->assertSee('Página rechazada')->assertSee('Rechazada')->assertDontSee('Página publicada');
    }

    public function test_search_and_status_cards_replace_the_old_filters_and_can_show_all_pages_again(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        Pagina::factory()->create(['nombre_publico' => 'Página publicada', 'publicacion_estado' => 'publicada']);
        Pagina::factory()->create(['nombre_publico' => 'Página por vender', 'publicacion_estado' => 'sin_publicar']);

        Livewire::test(PublicacionesSitio::class)
            ->assertSee('Buscar página o local')
            ->assertDontSee('Páginas y acceso público')
            ->assertDontSee('publication-filter')
            ->call('toggleFilter', 'published')
            ->assertSet('filter', 'published')
            ->assertSee('Página publicada')
            ->assertDontSee('Página por vender')
            ->call('toggleFilter', 'published')
            ->assertSet('filter', 'all')
            ->assertSee('Página por vender');
    }

    public function test_worker_monitor_shows_the_received_request_and_worker_signal(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        config(['clicktap.publication_slugs' => null]);
        $page = Pagina::factory()->create(['nombre_publico' => 'Página en cola']);
        app(PublicationQueue::class)->request($page);

        Livewire::test(PublicationWorkerMonitor::class)
            ->assertSee('Comunicación con el servidor')
            ->assertSee('Página en cola')
            ->assertSee('Solicitud recibida por el servidor')
            ->assertSee('Sin señal del worker');
    }

    public function test_unpublishing_a_sold_page_keeps_the_sale_confirmed(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        $page = Pagina::factory()->create(['publicacion_estado' => 'publicada', 'publicacion_commit' => 'abc', 'publicada_en' => now()]);
        $saleId = (string) Str::uuid();
        DB::table('ventas')->insert([
            'id' => $saleId, 'estado' => 'confirmada', 'pagina_id' => $page->id,
            'registrado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::test(PublicacionesSitio::class)->assertSee('Bajar de Cloudflare')->call('unpublishPage', $page->id)->assertHasNoErrors();

        $this->assertDatabaseHas('ventas', ['id' => $saleId, 'estado' => 'confirmada']);
        $this->assertDatabaseHas('paginas', ['id' => $page->id, 'activo' => false, 'publicacion_estado' => 'retirada_pendiente']);
        $this->assertSame('withdraw', app(PublicationQueue::class)->status()['pending']['type']);
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
}
