<?php

namespace Tests\Feature;

use App\Actions\RejectSaleAction;
use App\Filament\Pages\PublicacionesSitio;
use App\Models\Pagina;
use App\Models\User;
use App\Support\PublicationQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SaleRejectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rejecting_a_draft_preserves_its_history_and_queues_removal_without_stock_changes(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        $page = Pagina::factory()->create(['activo' => true, 'publicacion_estado' => 'enviada']);
        $saleId = $this->sale($actor, $page, 'borrador');
        $inventoryBefore = DB::table('inventario_movimientos')->count();

        $result = app(RejectSaleAction::class)->handle($actor, $saleId, 'El cliente no aceptó la propuesta.');

        $this->assertSame($page->id, $result?->id);
        $this->assertDatabaseHas('ventas', ['id' => $saleId, 'estado' => 'rechazada', 'pagina_id' => $page->id]);
        $this->assertDatabaseHas('paginas', ['id' => $page->id, 'activo' => false, 'publicacion_estado' => 'retirada_pendiente']);
        $this->assertDatabaseHas('eventos_auditoria', ['entidad_id' => $saleId, 'accion' => 'cliente_no_continua', 'motivo' => 'El cliente no aceptó la propuesta.']);
        $this->assertSame('withdraw', app(PublicationQueue::class)->status()['pending']['type']);
        $this->assertSame($inventoryBefore, DB::table('inventario_movimientos')->count());

        app(RejectSaleAction::class)->handle($actor, $saleId, 'Reintento del mismo rechazo');
        $this->assertSame(1, DB::table('eventos_auditoria')->where('entidad_id', $saleId)->count());
        $this->assertSame(1, app(PublicationQueue::class)->status()['pendingCount']);
        $this->assertSame($result->revision, $page->fresh()->revision);
    }

    public function test_a_confirmed_sale_requires_separate_financial_and_physical_resolution(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        $page = Pagina::factory()->create(['activo' => true]);
        $saleId = $this->sale($actor, $page, 'confirmada');

        app(RejectSaleAction::class)->handle($actor, $saleId, 'El cliente pidió anular la página.');

        $this->assertDatabaseHas('ventas', ['id' => $saleId, 'estado' => 'cancelacion_pendiente']);
        $this->assertDatabaseHas('paginas', ['id' => $page->id, 'activo' => false]);
        $this->assertSame(0, DB::table('inventario_movimientos')->count());
        $this->assertSame(0, DB::table('caja_movimientos')->count());
    }

    public function test_publications_lists_the_sale_and_can_withdraw_it(): void
    {
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        $page = Pagina::factory()->create(['slug' => 'visit-demo', 'nombre_publico' => 'Visita demo', 'activo' => true]);
        $saleId = $this->sale($actor, $page, 'borrador');

        Livewire::test(PublicacionesSitio::class)
            ->assertSee('Visita demo')
            ->assertSee('Borrador')
            ->set('withdrawalReasons.'.$page->id, 'El cliente decidió no continuar.')
            ->call('withdrawPage', $page->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ventas', ['id' => $saleId, 'estado' => 'rechazada']);
        $this->assertDatabaseHas('paginas', ['id' => $page->id, 'activo' => false]);
        $this->assertSame('withdraw', app(PublicationQueue::class)->status()['pending']['type']);
    }

    private function sale(User $actor, Pagina $page, string $state): string
    {
        $id = (string) Str::uuid();
        DB::table('ventas')->insert([
            'id' => $id,
            'estado' => $state,
            'pagina_id' => $page->id,
            'registrado_por' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
