<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BusinessLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_chips_are_aparted_once_and_acrylic_production_carries_real_costs(): void
    {
        $this->actingAs(User::factory()->create());
        $ledger = app(BusinessLedger::class);
        $ledger->initializeCatalog();
        $ledger->initializeCatalog();
        $this->assertDatabaseCount('inventario_productos', 4);

        $base = DB::table('inventario_productos')->where('sku', 'ACR-BASE')->value('id');
        $chip = DB::table('inventario_productos')->where('sku', 'NFC-CHIP')->value('id');
        $finished = DB::table('inventario_productos')->where('sku', 'ACR-PERSONALIZADO')->value('id');

        $ledger->receive($base, 20, 20000, 20000, 'Proveedor', 'F1', '2026-10-21', 'compra-base');
        $ledger->receive($chip, 50, 50000, 30000, 'Proveedor', 'F2', '2026-10-21', 'compra-chip');
        $ledger->reserveProduction(20, 'apartado');
        $ledger->reserveProduction(20, 'apartado');

        $this->assertDatabaseHas('inventario_productos', ['id' => $chip, 'existencia' => 50, 'reservado_produccion' => 20]);
        $this->assertSame(30, (int) DB::table('inventario_productos')->where('id', $chip)->first()->existencia - 20);

        $ledger->produce(20, 10000, true, '2026-10-21', 'produccion');
        $ledger->produce(20, 10000, true, '2026-10-21', 'produccion');
        $this->assertDatabaseHas('inventario_productos', ['id' => $base, 'existencia' => 0]);
        $this->assertDatabaseHas('inventario_productos', ['id' => $chip, 'existencia' => 30, 'reservado_produccion' => 0]);
        $this->assertDatabaseHas('inventario_productos', ['id' => $finished, 'existencia' => 20]);
        $this->assertDatabaseHas('inventario_lotes', ['producto_id' => $finished, 'costo_restante_clp' => 50000]);
        $this->assertDatabaseCount('caja_movimientos', 3);
    }

    public function test_reservation_delivery_payment_and_purchase_payment_keep_stock_and_cash_separate(): void
    {
        $this->actingAs(User::factory()->create());
        $ledger = app(BusinessLedger::class);
        $ledger->initializeCatalog();
        $card = DB::table('inventario_productos')->where('sku', 'TAR-GOOGLE')->value('id');
        $purchase = $ledger->receive($card, 2, 4000, 1000, null, null, '2026-10-21', 'cards');
        $order = $ledger->reserveOrder($card, 1, 5000, 'Cliente Uno', 'directo', null, null, 'order');

        $this->assertDatabaseHas('inventario_productos', ['id' => $card, 'existencia' => 2, 'reservado_pedidos' => 1]);
        $ledger->orderPayment($order, 2000, '2026-10-21', 'deposit');
        $ledger->fulfillOrder($order, 'delivered');
        $ledger->fulfillOrder($order, 'delivered');
        $ledger->orderPayment($order, 3000, '2026-10-21', 'final');
        $ledger->payPurchase($purchase, 3000, '2026-10-21', 'purchase-balance');
        $ledger->recordCash('aporte', 'capital', 25000, '2026-10-21', 'Capital inicial', 'capital');
        $ledger->recordCash('gasto', 'publicidad', 3000, '2026-10-21', 'Campaña', 'advertising');

        $this->assertDatabaseHas('inventario_productos', ['id' => $card, 'existencia' => 1, 'reservado_pedidos' => 0]);
        $this->assertDatabaseHas('inventario_pedidos', ['id' => $order, 'costo_vendido_clp' => 2000, 'estado' => 'entregado']);
        $finance = $ledger->finances();
        $this->assertSame(5000, $finance['revenue']);
        $this->assertSame(2000, $finance['knownCost']);
        $this->assertSame(3000, $finance['expenses']);
        $this->assertSame(0, $finance['receivables']);
        $this->assertSame(0, (int) $finance['payables']);
        $this->assertSame(23000, $finance['cash']);
    }

    public function test_over_reservation_and_unknown_costs_are_rejected_or_flagged(): void
    {
        $this->actingAs(User::factory()->create());
        $ledger = app(BusinessLedger::class);
        $ledger->initializeCatalog();
        $chip = DB::table('inventario_productos')->where('sku', 'NFC-CHIP')->value('id');
        $ledger->receive($chip, 1, null, 0, null, null, '2026-10-21', 'unknown-cost');
        $this->assertSame(1, $ledger->finances()['unknownLots']);
        $ledger->reserveOrder($chip, 1, 1000, null, 'directo', null, null, 'first');
        try {
            $ledger->reserveOrder($chip, 1, 1000, null, 'directo', null, null, 'second');
            $this->fail('Se permitió reservar una unidad dos veces.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventario_pedidos', 1);
        }
        $ledger->fulfillOrder(DB::table('inventario_pedidos')->value('id'), 'done');
        $this->assertSame(1, $ledger->finances()['unknownCosts']);
        $this->assertSame(0, $ledger->finances()['unknownLots']);
    }

    public function test_load_initial_inventory_sets_up_exact_50_cards_20_acrylics_and_50_chips_with_20_earmarked(): void
    {
        $this->actingAs(User::factory()->create());
        $ledger = app(BusinessLedger::class);
        $ledger->loadInitialInventory(50, 20, 50, 20);

        // Idempotency: second call does not duplicate
        $ledger->loadInitialInventory(50, 20, 50, 20);

        $card = DB::table('inventario_productos')->where('sku', 'TAR-GOOGLE')->first();
        $base = DB::table('inventario_productos')->where('sku', 'ACR-BASE')->first();
        $chip = DB::table('inventario_productos')->where('sku', 'NFC-CHIP')->first();
        $finished = DB::table('inventario_productos')->where('sku', 'ACR-PERSONALIZADO')->first();

        $this->assertSame(50, $card->existencia);
        $this->assertSame(0, $card->reservado_pedidos);
        $this->assertSame(0, $card->reservado_produccion);

        $this->assertSame(20, $base->existencia);
        $this->assertSame(0, $base->reservado_produccion);

        $this->assertSame(50, $chip->existencia);
        $this->assertSame(20, $chip->reservado_produccion);
        $this->assertSame(30, $chip->existencia - $chip->reservado_pedidos - $chip->reservado_produccion);

        $this->assertSame(0, $finished->existencia);

        $snapshot = $ledger->snapshot();
        $this->assertSame(50, $snapshot['breakdown']['disponible']['cards']);
        $this->assertSame(30, $snapshot['breakdown']['disponible']['chips']);
        $this->assertSame(20, $snapshot['production']['chipsApartados']);
        $this->assertSame(20, $snapshot['production']['capacidadEnsamblaje']);
    }

    public function test_inventory_adjustments_require_reason_and_update_stock_and_movements(): void
    {
        $this->actingAs(User::factory()->create());
        $ledger = app(BusinessLedger::class);
        $ledger->initializeCatalog();
        $chip = DB::table('inventario_productos')->where('sku', 'NFC-CHIP')->value('id');
        $ledger->receive($chip, 10, 10000, 10000, null, null, '2026-10-21', 'lot');

        // Test missing reason fails
        try {
            $ledger->adjust($chip, -2, '', 'adj-fail');
            $this->fail('Se permitió un ajuste sin motivo obligatorio.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('inventario_productos', ['id' => $chip, 'existencia' => 10]);
        }

        // Test merma adjustment
        $ledger->adjust($chip, -2, 'Chip roto durante control de calidad', 'merma-1');
        $this->assertDatabaseHas('inventario_productos', ['id' => $chip, 'existencia' => 8]);
        $this->assertDatabaseHas('inventario_movimientos', [
            'producto_id' => $chip,
            'tipo' => 'ajuste_merma',
            'cambio_existencia' => -2,
            'motivo' => 'Chip roto durante control de calidad',
        ]);

        // Test positive adjustment
        $ledger->adjust($chip, 1, 'Recuento físico encontró unidad extra', 'recuento-1');
        $this->assertDatabaseHas('inventario_productos', ['id' => $chip, 'existencia' => 9]);
    }

    public function test_order_payment_status_is_accurately_derived(): void
    {
        $this->actingAs(User::factory()->create());
        $ledger = app(BusinessLedger::class);
        $ledger->initializeCatalog();
        $card = DB::table('inventario_productos')->where('sku', 'TAR-GOOGLE')->value('id');
        $ledger->receive($card, 5, 20000, 20000, null, null, '2026-10-21', 'rcv');

        $order = $ledger->reserveOrder($card, 2, 10000, 'Cliente Test', 'directo', null, null, 'ord');

        // Initially unpaid
        $orders = collect($ledger->snapshot()['orders'])->keyBy('id');
        $this->assertSame('pendiente', $orders->get($order)->estado_pago);
        $this->assertSame(0, $orders->get($order)->pagado_clp);
        $this->assertSame(20000, $orders->get($order)->saldo_pendiente_clp);

        // Partial payment
        $ledger->orderPayment($order, 8000, '2026-10-21', 'pay-1');
        $orders = collect($ledger->snapshot()['orders'])->keyBy('id');
        $this->assertSame('parcial', $orders->get($order)->estado_pago);
        $this->assertSame(8000, $orders->get($order)->pagado_clp);
        $this->assertSame(12000, $orders->get($order)->saldo_pendiente_clp);

        // Final payment
        $ledger->orderPayment($order, 12000, '2026-10-21', 'pay-2');
        $orders = collect($ledger->snapshot()['orders'])->keyBy('id');
        $this->assertSame('pagado', $orders->get($order)->estado_pago);
        $this->assertSame(20000, $orders->get($order)->pagado_clp);
        $this->assertSame(0, $orders->get($order)->saldo_pendiente_clp);
    }

    public function test_administrator_can_open_stock_and_accounting_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/stock-tarjetas')->assertOk()->assertSee('Stock de tarjetas');
        $this->get('/admin/contabilidad')->assertOk()->assertSee('Contabilidad');
    }
}
