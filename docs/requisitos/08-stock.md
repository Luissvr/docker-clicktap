# 08 — Stock de tarjetas
Última revisión: 2026-11-09 · Estado: ✅

## Objetivo
Controlar productos, compras, pedidos/reservas, chips apartados y producción de acrílicos, con costos y stock siempre trazables.

## Actores
Administrador. Fachada: `BusinessLedger`.

## Requisitos
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| STK-01 | Registrar productos con nombre, SKU único, tipo (terminado/insumo), si es vendible, precio y mínimo de alerta. | 🟡 | Manual. |
| STK-02 | Registrar una compra (producto, cantidad, costo total, pagado, proveedor, documento, fecha) que suma stock y caja una sola vez. | ✅ | StockPurchasesTest: product_purchase_and_payment_flow_records_stock_and_cash_once |
| STK-03 | Registrar el pago de una compra pendiente y rechazar un sobrepago; un costo desconocido no se informa como gratis. | ✅ | StockPurchasesTest: unknown_cost_is_not_reported_as_free_and_overpayment_is_rejected |
| STK-04 | Mostrar un error y no cambiar stock ni caja ante una compra inválida. | ✅ | StockPurchasesTest: invalid_purchase_shows_an_error_and_does_not_change_stock_or_cash |
| STK-05 | Crear pedidos/reservas (producto vendible, cantidad, precio, cliente, canal, folio, abono inicial a caja) y revertir la reserva si el abono inicial es excesivo. | ✅ | StockPurchasesTest: excessive_initial_payment_rolls_back_the_reservation |
| STK-06 | Entregar, abonar, cancelar o reembolsar un pedido manteniendo separados stock y caja. | ✅ | BusinessLedgerTest: reservation_delivery_payment_and_purchase_payment_keep_stock_and_cash_separate |
| STK-07 | Derivar con exactitud el estado de pago de un pedido. | ✅ | BusinessLedgerTest: order_payment_status_is_accurately_derived |
| STK-08 | Rechazar reservas por encima del stock disponible y señalar costos desconocidos. | ✅ | BusinessLedgerTest: over_reservation_and_unknown_costs_are_rejected_or_flagged |
| STK-09 | Apartar chips para producción una sola vez, liberarlos a venta libre y registrar acrílicos terminados consumiendo 1 acrílico base + 1 chip apartado por unidad, con costo de impresión opcional (pagado o pendiente). | ✅ | BusinessLedgerTest: chips_are_aparted_once_and_acrylic_production_carries_real_costs |
| STK-10 | Ajustar inventario con sentido y motivo obligatorios (conteo, defecto, rotura), actualizando stock y movimientos. | ✅ | BusinessLedgerTest: inventory_adjustments_require_reason_and_update_stock_and_movements |
| STK-11 | Cargar el inventario inicial (50 tarjetas, 20 acrílicos, 50 chips, 20 reservados). | ✅ | BusinessLedgerTest: load_initial_inventory_sets_up_exact_50_cards_20_acrylics_and_50_chips_with_20_earmarked (cifras por conciliar) |
| STK-12 | Buscar en compras, pedidos y movimientos antiguos antes de paginar; paginar de a 25; conservar los filtros al cambiar de sección. | ✅ | StockPurchasesTest: search_reaches_old_purchases_orders_and_movements_before_pagination, stock_filters_survive_switching_sections |
| STK-13 | Reutilizar lecturas en caché y invalidarlas tras una compra. | ✅ | StockPurchasesTest: cached_reads_are_reused_and_invalidated_after_a_purchase |
| STK-14 | Mostrar una alerta visible cuando el stock cae bajo el mínimo. | 🟡 | Se captura el mínimo; la alerta no está verificada. |
| STK-15 | Abrir las pantallas de Stock y Contabilidad en el panel. | ✅ | AdminUiTest: stock_tarjetas_and_contabilidad_screens_load_in_admin_panel |

## Reglas de negocio
- Toda operación lleva clave de idempotencia.
- El stock **nunca** se decide desde la copia local (puede atrasarse hasta 20 minutos).
- Un costo desconocido es «desconocido», no cero.

## Limitaciones y pendientes
Conciliar cifras iniciales (STK-11); alerta de stock mínimo (STK-14); refactor de `BusinessLedger` por áreas (PEN-09).
