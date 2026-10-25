<?php

namespace Tests\Feature;

use App\Filament\Pages\StockTarjetas;
use App\Models\User;
use App\Support\BusinessLedger;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class StockPurchasesTest extends TestCase
{
    use DatabaseMigrations;

    public function test_product_purchase_and_payment_flow_records_stock_and_cash_once(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Livewire::test(StockTarjetas::class)->callAction('producto', data: [
            'sku' => 'tar-nueva', 'nombre' => 'Tarjeta nueva', 'tipo' => 'terminado',
            'vendible' => true, 'minimo' => 2, 'precio_clp' => 5000,
        ])->assertHasNoActionErrors();
        $product = DB::table('inventario_productos')->where('sku', 'TAR-NUEVA')->first();
        $this->assertNotNull($product);
        $this->assertSame(0, $product->existencia);
        $purchase = [
            'producto_id' => $product->id, 'cantidad' => 10, 'costo_total_clp' => 20000,
            'pagado_clp' => 5000, 'proveedor' => 'Proveedor prueba', 'documento' => 'FAC-123',
            'fecha' => '2026-10-23', 'clave_operacion' => 'purchase-test',
        ];
        $page->callAction('entrada', data: $purchase)->assertHasNoActionErrors();
        $page->callAction('entrada', data: $purchase)->assertHasNoActionErrors();
        $this->assertDatabaseCount('inventario_compras', 1);
        $this->assertDatabaseCount('inventario_lotes', 1);
        $this->assertDatabaseCount('caja_movimientos', 1);
        $this->assertDatabaseHas('inventario_productos', ['id' => $product->id, 'existencia' => 10]);
        $page->call('openPurchases')->assertSee('FAC-123')->assertSee('15.000');
        $purchaseId = DB::table('inventario_compras')->value('id');
        $page->callAction('pagarCompra', arguments: ['compra' => $purchaseId], data: [
            'monto_clp' => 15000, 'fecha' => '2026-10-23', 'clave_operacion' => 'balance-test',
        ])->assertHasNoActionErrors();
        $this->assertDatabaseHas('inventario_compras', ['id' => $purchaseId, 'pagado_clp' => 20000]);
        $this->assertSame(-20000, (int) DB::table('caja_movimientos')->sum('monto_clp'));
        $this->assertDatabaseHas('inventario_productos', ['id' => $product->id, 'existencia' => 10]);
    }

    public function test_cached_reads_are_reused_and_invalidated_after_a_purchase(): void
    {
        $this->actingAs(User::factory()->create());
        $ledger = app(BusinessLedger::class);
        $ledger->initializeCatalog();
        $before = $ledger->snapshot('inventario');
        DB::enableQueryLog();
        $ledger->snapshot('inventario');
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        $product = $before['products']->first();
        $ledger->receive($product->id, 3, null, 0, null, null, '2026-10-23', 'cache-purchase');
        $after = $ledger->snapshot('inventario')['products']->firstWhere('id', $product->id);
        $this->assertSame(3, $after->existencia);
    }

    public function test_search_reaches_old_purchases_orders_and_movements_before_pagination(): void
    {
        $this->actingAs(User::factory()->create());
        $ledger = app(BusinessLedger::class);
        $ledger->initializeCatalog();
        $product = DB::table('inventario_productos')->where('sku', 'TAR-GOOGLE')->value('id');
        $ledger->receive($product, 150, 150000, 0, 'Proveedor antiguo único', 'OLD', '2025-01-01', 'old');
        $oldOrder = $ledger->reserveOrder($product, 1, 2000, 'Cliente antiguo único', 'directo', null, null, 'old-order');
        $ledger->fulfillOrder($oldOrder, 'old-delivery');
        for ($i = 0; $i < 105; $i++) {
            $ledger->receive($product, 1, 1000, 0, 'Proveedor reciente', 'NEW-'.$i, '2026-10-23', 'new-'.$i);
            $ledger->reserveOrder($product, 1, 2000, 'Cliente reciente', 'directo', null, null, 'new-order-'.$i);
        }
        $this->assertCount(1, $ledger->purchases('Proveedor antiguo único')['purchases']);
        $orders = $ledger->snapshot('pedidos', ['buscar' => 'Cliente antiguo único']);
        $this->assertCount(1, $orders['orders']);
        $this->assertSame($oldOrder, $orders['orders']->first()->id);
        $this->assertCount(1, $ledger->snapshot('historial', ['buscar' => 'Proveedor antiguo único'])['movements']);
        $this->assertTrue($ledger->snapshot('pedidos')['hasMore']);
        $this->assertCount(25, $ledger->snapshot('pedidos')['orders']);
        $this->assertSame(1, $ledger->snapshot('inventario')['breakdown']['vendido']['total']);
    }

    public function test_unknown_cost_is_not_reported_as_free_and_overpayment_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $ledger = app(BusinessLedger::class);
        $ledger->initializeCatalog();
        $product = DB::table('inventario_productos')->value('id');
        $id = $ledger->receive($product, 1, null, 0, null, null, '2026-10-23', 'unknown');
        Livewire::test(StockTarjetas::class)->call('openPurchases')->assertSee('Por confirmar');
        $this->expectException(ValidationException::class);
        $ledger->payPurchase($id, 1, '2026-10-23', 'invalid');
    }

    public function test_stock_filters_survive_switching_sections(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(StockTarjetas::class)->set('buscar', 'Tarjeta')->set('activeTab', 'historial')
            ->assertSet('buscar', 'Tarjeta')->assertSet('stockPage', 1);
        Livewire::test(StockTarjetas::class)->assertSet('buscar', 'Tarjeta')->assertSet('activeTab', 'historial');
    }

    public function test_invalid_purchase_shows_an_error_and_does_not_change_stock_or_cash(): void
    {
        $this->actingAs(User::factory()->create());
        app(BusinessLedger::class)->initializeCatalog();
        $product = DB::table('inventario_productos')->value('id');
        Livewire::test(StockTarjetas::class)->callAction('entrada', data: [
            'producto_id' => $product, 'cantidad' => 10, 'costo_total_clp' => 1000,
            'pagado_clp' => 2000, 'fecha' => '2026-10-23', 'clave_operacion' => 'invalid',
        ])->assertHasActionErrors(['pagado_clp']);
        $this->assertDatabaseCount('inventario_compras', 0);
        $this->assertDatabaseCount('caja_movimientos', 0);
        $this->assertDatabaseHas('inventario_productos', ['id' => $product, 'existencia' => 0]);
    }

    public function test_excessive_initial_payment_rolls_back_the_reservation(): void
    {
        $this->actingAs(User::factory()->create());
        $ledger = app(BusinessLedger::class);
        $ledger->initializeCatalog();
        $product = DB::table('inventario_productos')->where('sku', 'TAR-GOOGLE')->value('id');
        $ledger->receive($product, 1, 1000, 0, null, null, '2026-10-23', 'setup');
        Livewire::test(StockTarjetas::class)->callAction('reservar', data: [
            'producto_id' => $product, 'cantidad' => 1, 'precio_unitario_clp' => 2000,
            'canal' => 'directo', 'abono_inicial_clp' => 3000, 'clave_operacion' => 'invalid-reservation',
        ])->assertHasActionErrors();
        $this->assertDatabaseCount('inventario_pedidos', 0);
        $this->assertDatabaseHas('inventario_productos', ['id' => $product, 'existencia' => 1, 'reservado_pedidos' => 0]);
    }
}
