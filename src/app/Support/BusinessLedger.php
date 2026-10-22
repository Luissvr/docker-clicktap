<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BusinessLedger
{
    public function connection(): Connection
    {
        return DB::connection(CatalogConnection::enabled() ? 'supabase' : config('database.default'));
    }

    public function initializeCatalog(): void
    {
        $items = [
            ['TAR-GOOGLE', 'Tarjeta Google', 'terminado', true],
            ['ACR-BASE', 'Acrílico sin NFC', 'insumo', false],
            ['NFC-CHIP', 'Chip NFC', 'insumo', true],
            ['ACR-PERSONALIZADO', 'Acrílico personalizado con NFC', 'terminado', true],
        ];

        $this->connection()->transaction(function () use ($items): void {
            foreach ($items as [$sku, $name, $type, $saleable]) {
                $this->connection()->table('inventario_productos')->insertOrIgnore([
                    'id' => (string) Str::uuid(), 'sku' => $sku, 'nombre' => $name,
                    'tipo' => $type, 'vendible' => $saleable,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }, 3);
    }

    public function setPrice(string $productId, ?int $price): void
    {
        $this->connection()->table('inventario_productos')->where('id', $productId)
            ->update(['precio_clp' => $price, 'updated_at' => now()]);
    }

    public function receive(string $productId, int $quantity, ?int $totalCost, int $paid, ?string $supplier, ?string $document, string $date, string $key): string
    {
        $this->positive($quantity, 'cantidad');
        $this->nonnegative($totalCost, 'costo_total_clp');
        $this->nonnegative($paid, 'pagado_clp');
        if (($totalCost === null && $paid !== 0) || ($totalCost !== null && $paid > $totalCost)) {
            $this->invalid('pagado_clp', 'El pago requiere un costo conocido y no puede superar la compra.');
        }

        return $this->connection()->transaction(function () use ($productId, $quantity, $totalCost, $paid, $supplier, $document, $date, $key): string {
            $prior = $this->connection()->table('inventario_compras')->where('clave_operacion', $key)->first();
            if ($prior) {
                return $prior->id;
            }
            $product = $this->product($productId);
            $id = (string) Str::uuid();
            $this->connection()->table('inventario_compras')->insert([
                'id' => $id, 'producto_id' => $productId, 'cantidad' => $quantity,
                'costo_total_clp' => $totalCost, 'pagado_clp' => $paid,
                'proveedor' => $supplier, 'documento' => $document, 'fecha' => $date,
                'clave_operacion' => $key, 'registrado_por' => auth()->id(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->lot($productId, $id, $quantity, $totalCost);
            $this->change($product, $quantity);
            $this->movement($productId, 'entrada', $quantity, 0, 0, $totalCost, $key, $id, null, $supplier);
            if ($paid > 0) {
                $this->cash('pago_compra', 'inventario', -$paid, $date, 'Compra de '.$product->nombre, $key.':pago', null, $id, $document);
            }

            return $id;
        }, 3);
    }

    public function payPurchase(string $purchaseId, int $amount, string $date, string $key): void
    {
        $this->positive($amount, 'monto_clp');
        $this->connection()->transaction(function () use ($purchaseId, $amount, $date, $key): void {
            if ($this->cashExists($key)) {
                return;
            }
            $purchase = $this->connection()->table('inventario_compras')->where('id', $purchaseId)->lockForUpdate()->first();
            if (! $purchase || $purchase->costo_total_clp === null || $purchase->pagado_clp + $amount > $purchase->costo_total_clp) {
                $this->invalid('monto_clp', 'El pago supera el saldo de la compra.');
            }
            $this->connection()->table('inventario_compras')->where('id', $purchaseId)
                ->update(['pagado_clp' => $purchase->pagado_clp + $amount, 'updated_at' => now()]);
            $this->cash('pago_compra', 'inventario', -$amount, $date, 'Pago pendiente de compra', $key, null, $purchaseId);
        }, 3);
    }

    public function reserveProduction(int $quantity, string $key, bool $release = false): void
    {
        $this->positive($quantity, 'cantidad');
        $this->connection()->transaction(function () use ($quantity, $key, $release): void {
            if ($this->movementExists($key)) {
                return;
            }
            $chip = $this->sku('NFC-CHIP');
            if ($release) {
                if ($chip->reservado_produccion < $quantity) {
                    $this->invalid('cantidad', 'No hay tantos chips apartados.');
                }
            } else {
                $this->available($chip, $quantity);
            }
            $change = $release ? -$quantity : $quantity;
            $this->change($chip, 0, 0, $change);
            $this->movement($chip->id, $release ? 'liberacion_produccion' : 'apartado_produccion', 0, 0, $change, null, $key);
        }, 3);
    }

    public function produce(int $quantity, ?int $printingCost, bool $printingPaid, string $date, string $key): void
    {
        $this->positive($quantity, 'cantidad');
        $this->nonnegative($printingCost, 'impresion_clp');
        if ($printingPaid && $printingCost === null) {
            $this->invalid('impresion_clp', 'Indica el costo de impresión pagado.');
        }
        $this->connection()->transaction(function () use ($quantity, $printingCost, $printingPaid, $date, $key): void {
            if ($this->movementExists($key)) {
                return;
            }
            $base = $this->sku('ACR-BASE');
            $chip = $this->sku('NFC-CHIP');
            $finished = $this->sku('ACR-PERSONALIZADO');
            $this->available($base, $quantity);
            if ($chip->reservado_produccion < $quantity) {
                $this->invalid('cantidad', 'Aparta primero los chips para producción.');
            }
            $baseCost = $this->consume($base->id, $quantity);
            $chipCost = $this->consume($chip->id, $quantity);
            $cost = $baseCost === null || $chipCost === null || $printingCost === null ? null : $baseCost + $chipCost + $printingCost;
            $this->change($base, -$quantity);
            $this->change($chip, -$quantity, 0, -$quantity);
            $this->change($finished, $quantity);
            $this->lot($finished->id, null, $quantity, $cost);
            $this->movement($base->id, 'consumo_produccion', -$quantity, 0, 0, $baseCost, $key);
            $this->movement($chip->id, 'consumo_produccion', -$quantity, 0, -$quantity, $chipCost, $key);
            $this->movement($finished->id, 'produccion', $quantity, 0, 0, $cost, $key, null, null, 'Impresión '.($printingCost === null ? 'pendiente' : '$'.$printingCost));
            if ($printingPaid && $printingCost > 0) {
                $this->cash('pago_produccion', 'impresion', -$printingCost, $date, 'Impresión de acrílicos', $key.':pago');
            }
        }, 3);
    }

    public function reserveOrder(string $productId, int $quantity, int $price, ?string $customer, string $channel, ?string $external, ?string $expires, string $key): string
    {
        $this->positive($quantity, 'cantidad');
        $this->nonnegative($price, 'precio_unitario_clp');

        return $this->connection()->transaction(function () use ($productId, $quantity, $price, $customer, $channel, $external, $expires, $key): string {
            $prior = $this->connection()->table('inventario_pedidos')->where('clave_operacion', $key)->first();
            if ($prior) {
                return $prior->id;
            }
            $product = $this->product($productId);
            if (! $product->vendible) {
                $this->invalid('producto_id', 'Este insumo no se vende directamente.');
            }
            $this->available($product, $quantity);
            $id = (string) Str::uuid();
            $this->connection()->table('inventario_pedidos')->insert([
                'id' => $id, 'producto_id' => $productId, 'cantidad' => $quantity,
                'precio_unitario_clp' => $price, 'cliente' => $customer, 'canal' => $channel,
                'referencia_externa' => $external, 'reservado_hasta' => $expires,
                'clave_operacion' => $key, 'registrado_por' => auth()->id(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->change($product, 0, $quantity);
            $this->movement($productId, 'reserva_pedido', 0, $quantity, 0, null, $key, null, $id);

            return $id;
        }, 3);
    }

    public function fulfillOrder(string $orderId, string $key): void
    {
        $this->connection()->transaction(function () use ($orderId, $key): void {
            if ($this->movementExists($key)) {
                return;
            }
            $order = $this->order($orderId);
            if ($order->estado !== 'reservado') {
                $this->invalid('pedido', 'Solo se puede entregar una reserva activa.');
            }
            $product = $this->product($order->producto_id);
            $cost = $this->consume($product->id, $order->cantidad);
            $this->change($product, -$order->cantidad, -$order->cantidad);
            $this->connection()->table('inventario_pedidos')->where('id', $orderId)->update([
                'estado' => 'entregado', 'costo_vendido_clp' => $cost,
                'entregado_en' => now(), 'updated_at' => now(),
            ]);
            $this->movement($product->id, 'venta_entregada', -$order->cantidad, -$order->cantidad, 0, $cost, $key, null, $orderId);
        }, 3);
    }

    public function cancelOrder(string $orderId, string $key): void
    {
        $this->connection()->transaction(function () use ($orderId, $key): void {
            if ($this->movementExists($key)) {
                return;
            }
            $order = $this->order($orderId);
            if ($order->estado !== 'reservado') {
                $this->invalid('pedido', 'Solo se puede cancelar una reserva activa.');
            }
            if ($this->paidForOrder($orderId) !== 0) {
                $this->invalid('pedido', 'Reembolsa el abono antes de cancelar.');
            }
            $product = $this->product($order->producto_id);
            $this->change($product, 0, -$order->cantidad);
            $this->connection()->table('inventario_pedidos')->where('id', $orderId)
                ->update(['estado' => 'cancelado', 'updated_at' => now()]);
            $this->movement($product->id, 'cancelacion_reserva', 0, -$order->cantidad, 0, null, $key, null, $orderId);
        }, 3);
    }

    public function orderPayment(string $orderId, int $amount, string $date, string $key, bool $refund = false): void
    {
        $this->positive($amount, 'monto_clp');
        $this->connection()->transaction(function () use ($orderId, $amount, $date, $key, $refund): void {
            if ($this->cashExists($key)) {
                return;
            }
            $order = $this->order($orderId);
            if ($order->estado === 'cancelado' || ($refund && $order->estado !== 'reservado')) {
                $this->invalid('pedido', 'Este pedido no admite esa operación de pago.');
            }
            $paid = $this->paidForOrder($orderId);
            if (($refund && $amount > $paid) || (! $refund && $amount > $order->cantidad * $order->precio_unitario_clp - $paid)) {
                $this->invalid('monto_clp', 'El monto supera el saldo permitido.');
            }
            $this->cash($refund ? 'reembolso' : 'cobro_venta', 'venta', $refund ? -$amount : $amount,
                $date, $refund ? 'Reembolso de reserva' : 'Cobro de pedido', $key, $orderId);
        }, 3);
    }

    public function recordCash(string $type, string $category, int $amount, string $date, string $description, string $key, ?string $document = null): void
    {
        if (! in_array($type, ['aporte', 'gasto', 'retiro'], true)) {
            $this->invalid('tipo', 'Tipo de movimiento no permitido.');
        }
        $this->positive($amount, 'monto_clp');
        $this->connection()->transaction(function () use ($type, $category, $amount, $date, $description, $key, $document): void {
            if ($this->cashExists($key)) {
                return;
            }
            $this->cash($type, $category, $type === 'aporte' ? $amount : -$amount,
                $date, $description, $key, null, null, $document);
        }, 3);
    }

    public function loadInitialInventory(int $cardsQty = 50, int $acrylicsQty = 20, int $chipsQty = 50, int $chipsReservedForAcrylics = 20, ?int $totalCost = null, string $date = '', string $keyPrefix = 'apertura'): void
    {
        $this->initializeCatalog();
        $date = $date ?: now('America/Santiago')->toDateString();

        $this->connection()->transaction(function () use ($cardsQty, $acrylicsQty, $chipsQty, $chipsReservedForAcrylics, $date, $keyPrefix): void {
            $card = $this->sku('TAR-GOOGLE');
            $base = $this->sku('ACR-BASE');
            $chip = $this->sku('NFC-CHIP');

            if ($this->movementExists($keyPrefix.':cards') || $card->existencia > 0 || $base->existencia > 0 || $chip->existencia > 0) {
                return;
            }

            $this->receive($card->id, $cardsQty, null, 0, 'Inventario inicial informado', 'Apertura', $date, $keyPrefix.':cards');
            $this->receive($base->id, $acrylicsQty, null, 0, 'Inventario inicial informado', 'Apertura', $date, $keyPrefix.':acrylics');
            $this->receive($chip->id, $chipsQty, null, 0, 'Inventario inicial informado', 'Apertura', $date, $keyPrefix.':chips');

            if ($chipsReservedForAcrylics > 0) {
                $this->reserveProduction($chipsReservedForAcrylics, $keyPrefix.':apartado');
            }
        }, 3);
    }

    public function adjust(string $productId, int $difference, string $reason, string $key): void
    {
        if ($difference === 0) {
            $this->invalid('cantidad', 'El ajuste debe ser distinto de cero.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            $this->invalid('motivo', 'El motivo del ajuste es obligatorio.');
        }

        $this->connection()->transaction(function () use ($productId, $difference, $reason, $key): void {
            if ($this->movementExists($key)) {
                return;
            }
            $product = $this->product($productId);
            if ($difference < 0) {
                $absDiff = -$difference;
                $this->available($product, $absDiff);
                $cost = $this->consume($product->id, $absDiff);
                $this->change($product, $difference);
                $this->movement($product->id, 'ajuste_merma', $difference, 0, 0, $cost, $key, null, null, $reason);
            } else {
                $this->lot($product->id, null, $difference, null);
                $this->change($product, $difference);
                $this->movement($product->id, 'ajuste_positivo', $difference, 0, 0, null, $key, null, null, $reason);
            }
        }, 3);
    }

    public function snapshot(): array
    {
        $db = $this->connection();
        $products = $db->table('inventario_productos')->orderBy('sku')->get();

        $rawOrders = $db->table('inventario_pedidos as p')->join('inventario_productos as i', 'i.id', '=', 'p.producto_id')
            ->leftJoinSub($this->orderPayments(), 'payments', 'payments.pedido_id', '=', 'p.id')
            ->select('p.*', 'i.nombre as producto_nombre', 'i.sku as producto_sku')
            ->selectRaw('COALESCE(payments.pagado, 0) AS pagado_clp')
            ->orderByDesc('p.created_at')->limit(100)->get();

        $orders = $rawOrders->map(function ($order) {
            $paid = (int) $order->pagado_clp;
            $total = $order->cantidad * $order->precio_unitario_clp;
            $order->total_clp = $total;
            $order->pagado_clp = $paid;
            $order->saldo_pendiente_clp = max(0, $total - $paid);
            if ($order->estado === 'cancelado') {
                $order->estado_pago = $paid > 0 ? 'con_abono' : 'sin_cobro';
            } elseif ($paid >= $total && $total > 0) {
                $order->estado_pago = 'pagado';
            } elseif ($paid > 0) {
                $order->estado_pago = 'parcial';
            } else {
                $order->estado_pago = 'pendiente';
            }

            return $order;
        });

        $bySku = $products->keyBy('sku');
        $card = $bySku->get('TAR-GOOGLE');
        $base = $bySku->get('ACR-BASE');
        $chip = $bySku->get('NFC-CHIP');
        $finished = $bySku->get('ACR-PERSONALIZADO');

        $deliveredOrders = $orders->where('estado', 'entregado');
        $deliveredCards = $deliveredOrders->where('producto_sku', 'TAR-GOOGLE')->sum('cantidad');
        $deliveredChips = $deliveredOrders->where('producto_sku', 'NFC-CHIP')->sum('cantidad');
        $deliveredAcrylics = $deliveredOrders->where('producto_sku', 'ACR-PERSONALIZADO')->sum('cantidad');

        $productionMovements = $db->table('inventario_movimientos as m')
            ->join('inventario_productos as i', 'i.id', '=', 'm.producto_id')
            ->whereIn('m.tipo', ['apartado_produccion', 'liberacion_produccion', 'consumo_produccion', 'produccion'])
            ->select('m.*', 'i.nombre as producto_nombre', 'i.sku as producto_sku')
            ->orderByDesc('m.created_at')->limit(50)->get();

        return [
            'products' => $products,
            'orders' => $orders,
            'movements' => $db->table('inventario_movimientos as m')->join('inventario_productos as i', 'i.id', '=', 'm.producto_id')
                ->select('m.*', 'i.nombre as producto_nombre', 'i.sku as producto_sku')->orderByDesc('m.created_at')->limit(100)->get(),
            'breakdown' => [
                'disponible' => [
                    'total' => $products->sum(fn ($p) => max(0, $p->existencia - $p->reservado_pedidos - $p->reservado_produccion)),
                    'cards' => $card ? max(0, $card->existencia - $card->reservado_pedidos - $card->reservado_produccion) : 0,
                    'bases' => $base ? max(0, $base->existencia - $base->reservado_pedidos - $base->reservado_produccion) : 0,
                    'chips' => $chip ? max(0, $chip->existencia - $chip->reservado_pedidos - $chip->reservado_produccion) : 0,
                    'finished' => $finished ? max(0, $finished->existencia - $finished->reservado_pedidos - $finished->reservado_produccion) : 0,
                ],
                'reservado' => [
                    'total' => $products->sum('reservado_pedidos'),
                    'cards' => $card?->reservado_pedidos ?? 0,
                    'chips' => $chip?->reservado_pedidos ?? 0,
                    'finished' => $finished?->reservado_pedidos ?? 0,
                ],
                'proceso' => [
                    'total' => ($base ? max(0, $base->existencia - $base->reservado_pedidos) : 0) + ($chip?->reservado_produccion ?? 0),
                    'bases' => $base ? max(0, $base->existencia - $base->reservado_pedidos) : 0,
                    'chips_apartados' => $chip?->reservado_produccion ?? 0,
                    'capacidad_acrilicos' => ($base && $chip) ? min(max(0, $base->existencia - $base->reservado_pedidos), $chip->reservado_produccion) : 0,
                ],
                'vendido' => [
                    'total' => $deliveredOrders->sum('cantidad'),
                    'cards' => $deliveredCards,
                    'chips' => $deliveredChips,
                    'finished' => $deliveredAcrylics,
                ],
            ],
            'production' => [
                'baseStock' => $base?->existencia ?? 0,
                'chipsApartados' => $chip?->reservado_produccion ?? 0,
                'chipsLibres' => $chip ? max(0, $chip->existencia - $chip->reservado_pedidos - $chip->reservado_produccion) : 0,
                'acrilicosTerminados' => $finished?->existencia ?? 0,
                'capacidadEnsamblaje' => ($base && $chip) ? min(max(0, $base->existencia - $base->reservado_pedidos), $chip->reservado_produccion) : 0,
                'movements' => $productionMovements,
            ],
        ];
    }

    public function finances(?string $from = null, ?string $to = null): array
    {
        $db = $this->connection();
        $salesQuery = $db->table('inventario_pedidos')->where('estado', 'entregado');
        if ($from) {
            $salesQuery->whereDate('entregado_en', '>=', $from);
        }
        if ($to) {
            $salesQuery->whereDate('entregado_en', '<=', $to);
        }
        $sales = $salesQuery->get();
        $lots = $db->table('inventario_lotes')->where('cantidad_restante', '>', 0)->get();
        $purchases = $db->table('inventario_compras as c')
            ->join('inventario_productos as i', 'i.id', '=', 'c.producto_id')
            ->select('c.*', 'i.nombre as producto_nombre', 'i.sku as producto_sku')
            ->orderByDesc('c.created_at')->limit(100)->get();
        $periodCash = $db->table('caja_movimientos');
        if ($from) {
            $periodCash->whereDate('fecha', '>=', $from);
        }
        if ($to) {
            $periodCash->whereDate('fecha', '<=', $to);
        }
        $periodEntries = $periodCash->orderByDesc('fecha')->orderByDesc('created_at')->get();
        $allOrders = $db->table('inventario_pedidos as p')->join('inventario_productos as i', 'i.id', '=', 'p.producto_id')
            ->leftJoinSub($this->orderPayments(), 'payments', 'payments.pedido_id', '=', 'p.id')
            ->select('p.*', 'i.nombre as producto_nombre', 'i.sku as producto_sku')
            ->selectRaw('COALESCE(payments.pagado, 0) AS pagado_clp')
            ->orderByDesc('p.created_at')->limit(100)->get()
            ->map(function ($order) {
                $paid = (int) $order->pagado_clp;
                $total = $order->cantidad * $order->precio_unitario_clp;
                $order->total_clp = $total;
                $order->pagado_clp = $paid;
                $order->saldo_pendiente_clp = max(0, $total - $paid);
                if ($order->estado === 'cancelado') {
                    $order->estado_pago = $paid > 0 ? 'con_abono' : 'sin_cobro';
                } elseif ($paid >= $total && $total > 0) {
                    $order->estado_pago = 'pagado';
                } elseif ($paid > 0) {
                    $order->estado_pago = 'parcial';
                } else {
                    $order->estado_pago = 'pendiente';
                }

                return $order;
            });

        $products = $db->table('inventario_productos')->orderBy('sku')->get();

        $productMargins = $products->where('vendible', true)->map(function ($prod) use ($sales) {
            $prodSales = $sales->where('producto_id', $prod->id);
            $qty = $prodSales->sum('cantidad');
            $revenue = $prodSales->sum(fn ($o) => $o->cantidad * $o->precio_unitario_clp);
            $cost = $prodSales->whereNotNull('costo_vendido_clp')->sum('costo_vendido_clp');
            $hasUnknown = $prodSales->whereNull('costo_vendido_clp')->isNotEmpty();
            $gross = $hasUnknown ? null : $revenue - $cost;
            $unitMargin = ($qty > 0 && $gross !== null) ? intdiv($gross, $qty) : null;
            $marginPct = ($revenue > 0 && $gross !== null) ? round(($gross / $revenue) * 100, 1) : null;

            return (object) [
                'id' => $prod->id,
                'sku' => $prod->sku,
                'nombre' => $prod->nombre,
                'precio_sugerido' => $prod->precio_clp,
                'unidades_vendidas' => $qty,
                'ingresos' => $revenue,
                'costo' => $cost,
                'tiene_costos_pendientes' => $hasUnknown,
                'margen_bruto' => $gross,
                'margen_unitario' => $unitMargin,
                'margen_porcentaje' => $marginPct,
            ];
        });

        $bySku = $products->keyBy('sku');
        $base = $bySku->get('ACR-BASE');
        $chips = $bySku->get('NFC-CHIP');
        $plannedAcrylics = ($base && $chips) ? min(max(0, $base->existencia - $base->reservado_pedidos), $chips->reservado_produccion) : 0;

        $stockAlerts = $products->filter(function ($p) {
            $free = max(0, $p->existencia - $p->reservado_pedidos - $p->reservado_produccion);

            return $p->minimo > 0 && $free <= $p->minimo;
        });

        $receivables = $db->table('inventario_pedidos as p')
            ->leftJoinSub($this->orderPayments(), 'payments', 'payments.pedido_id', '=', 'p.id')
            ->where('p.estado', 'entregado')
            ->selectRaw('COALESCE(SUM(p.cantidad * p.precio_unitario_clp - COALESCE(payments.pagado, 0)), 0) AS saldo')
            ->value('saldo');

        return [
            'cash' => $db->table('caja_movimientos')->sum('monto_clp'),
            'revenue' => $sales->sum(fn ($order): int => $order->cantidad * $order->precio_unitario_clp),
            'knownCost' => $sales->whereNotNull('costo_vendido_clp')->sum('costo_vendido_clp'),
            'unknownCosts' => $sales->whereNull('costo_vendido_clp')->count(),
            'expenses' => -$periodEntries->where('tipo', 'gasto')->sum('monto_clp'),
            'collections' => $periodEntries->where('tipo', 'cobro_venta')->sum('monto_clp'),
            'receivables' => (int) $receivables,
            'payables' => $db->table('inventario_compras')->whereNotNull('costo_total_clp')
                ->selectRaw('COALESCE(SUM(costo_total_clp - pagado_clp), 0) AS saldo')->value('saldo'),
            'inventoryValue' => $lots->whereNotNull('costo_restante_clp')->sum('costo_restante_clp'),
            'unknownLots' => $lots->whereNull('costo_restante_clp')->count(),
            'entries' => $periodEntries->take(100),
            'purchases' => $purchases,
            'orders' => $allOrders,
            'products' => $products->where('vendible', true),
            'allProducts' => $products,
            'productMargins' => $productMargins,
            'plannedAcrylics' => $plannedAcrylics,
            'stockAlerts' => $stockAlerts,
        ];
    }

    private function product(string $id): object
    {
        $product = $this->connection()->table('inventario_productos')->where('id', $id)->lockForUpdate()->first();
        if (! $product || ! $product->activo) {
            $this->invalid('producto_id', 'El producto no está disponible.');
        }

        return $product;
    }

    private function sku(string $sku): object
    {
        $product = $this->connection()->table('inventario_productos')->where('sku', $sku)->lockForUpdate()->first();
        if (! $product) {
            $this->invalid('producto_id', 'Inicializa primero el catálogo.');
        }

        return $product;
    }

    private function order(string $id): object
    {
        $order = $this->connection()->table('inventario_pedidos')->where('id', $id)->lockForUpdate()->first();
        if (! $order) {
            $this->invalid('pedido', 'El pedido no existe.');
        }

        return $order;
    }

    private function available(object $product, int $quantity): void
    {
        if ($product->existencia - $product->reservado_pedidos - $product->reservado_produccion < $quantity) {
            $this->invalid('cantidad', 'No hay existencias libres suficientes.');
        }
    }

    private function change(object $product, int $stock, int $orders = 0, int $production = 0): void
    {
        $onHand = $product->existencia + $stock;
        $heldOrders = $product->reservado_pedidos + $orders;
        $heldProduction = $product->reservado_produccion + $production;
        if ($onHand < 0 || $heldOrders < 0 || $heldProduction < 0 || $heldOrders + $heldProduction > $onHand) {
            $this->invalid('cantidad', 'La operación dejaría un saldo de stock inválido.');
        }
        $this->connection()->table('inventario_productos')->where('id', $product->id)->update([
            'existencia' => $onHand, 'reservado_pedidos' => $heldOrders,
            'reservado_produccion' => $heldProduction, 'updated_at' => now(),
        ]);
    }

    private function lot(string $productId, ?string $purchaseId, int $quantity, ?int $cost): void
    {
        $this->connection()->table('inventario_lotes')->insert([
            'id' => (string) Str::uuid(), 'producto_id' => $productId, 'compra_id' => $purchaseId,
            'cantidad_inicial' => $quantity, 'cantidad_restante' => $quantity,
            'costo_restante_clp' => $cost, 'recibido_en' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function consume(string $productId, int $quantity): ?int
    {
        $remaining = $quantity;
        $cost = 0;
        $known = true;
        $lots = $this->connection()->table('inventario_lotes')->where('producto_id', $productId)
            ->where('cantidad_restante', '>', 0)->orderBy('recibido_en')->orderBy('id')->lockForUpdate()->get();
        foreach ($lots as $lot) {
            if ($remaining === 0) {
                break;
            }
            $taken = min($remaining, $lot->cantidad_restante);
            $takenCost = $lot->costo_restante_clp === null ? null : ($taken === $lot->cantidad_restante
                ? $lot->costo_restante_clp : intdiv($lot->costo_restante_clp * $taken, $lot->cantidad_restante));
            $known = $known && $takenCost !== null;
            $cost += $takenCost ?? 0;
            $this->connection()->table('inventario_lotes')->where('id', $lot->id)->update([
                'cantidad_restante' => $lot->cantidad_restante - $taken,
                'costo_restante_clp' => $takenCost === null ? null : $lot->costo_restante_clp - $takenCost,
                'updated_at' => now(),
            ]);
            $remaining -= $taken;
        }
        if ($remaining !== 0) {
            $this->invalid('cantidad', 'Los lotes no coinciden con la existencia.');
        }

        return $known ? $cost : null;
    }

    private function movement(string $productId, string $type, int $stock, int $orders, int $production, ?int $cost, string $key, ?string $purchaseId = null, ?string $orderId = null, ?string $reason = null): void
    {
        $this->connection()->table('inventario_movimientos')->insert([
            'id' => (string) Str::uuid(), 'producto_id' => $productId, 'tipo' => $type,
            'cambio_existencia' => $stock, 'cambio_reserva_pedido' => $orders,
            'cambio_reserva_produccion' => $production, 'costo_clp' => $cost,
            'pedido_id' => $orderId, 'compra_id' => $purchaseId, 'motivo' => $reason,
            'clave_operacion' => $key, 'registrado_por' => auth()->id(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function cash(string $type, string $category, int $amount, string $date, string $description, string $key, ?string $orderId = null, ?string $purchaseId = null, ?string $document = null): void
    {
        $this->connection()->table('caja_movimientos')->insert([
            'id' => (string) Str::uuid(), 'tipo' => $type, 'categoria' => $category,
            'monto_clp' => $amount, 'fecha' => $date, 'descripcion' => $description,
            'documento' => $document, 'pedido_id' => $orderId, 'compra_id' => $purchaseId,
            'clave_operacion' => $key, 'registrado_por' => auth()->id(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function paidForOrder(string $orderId): int
    {
        return (int) $this->connection()->table('caja_movimientos')->where('pedido_id', $orderId)->sum('monto_clp');
    }

    private function orderPayments(): Builder
    {
        return $this->connection()->table('caja_movimientos')
            ->selectRaw('pedido_id, SUM(monto_clp) AS pagado')
            ->whereNotNull('pedido_id')->groupBy('pedido_id');
    }

    private function movementExists(string $key): bool
    {
        return $this->connection()->table('inventario_movimientos')->where('clave_operacion', $key)->exists();
    }

    private function cashExists(string $key): bool
    {
        return $this->connection()->table('caja_movimientos')->where('clave_operacion', $key)->exists();
    }

    private function positive(int $value, string $field): void
    {
        if ($value <= 0) {
            $this->invalid($field, 'Ingresa un valor mayor que cero.');
        }
    }

    private function nonnegative(?int $value, string $field): void
    {
        if ($value !== null && $value < 0) {
            $this->invalid($field, 'Ingresa un valor igual o mayor que cero.');
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
