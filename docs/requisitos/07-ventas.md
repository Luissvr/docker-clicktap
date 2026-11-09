# 07 — Ventas de tarjeta Google
Última revisión: 2026-11-09 · Estado: ✅

## Objetivo
Registrar la venta en terreno de la tarjeta Google, descontar stock y marcar la página como vendida.

## Actores
Administrador.

## Requisitos
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| VEN-01 | Crear una venta en borrador con cliente, local (opcional) y forma de cobro: 1 × $15.000, 2 × $20.000 o cantidad y precio total libres. | 🟡 | Manual (las cifras fijas están en `Ventas`). |
| VEN-02 | Al confirmar, validar que la cantidad sea la suma de los ítems, que los ítems sean solo del producto `TAR-GOOGLE` y que el total sea Σ cantidad × precio. | ✅ | BusinessLedgerTest: over_reservation_and_unknown_costs_are_rejected_or_flagged |
| VEN-03 | Confirmar la venta reservando y entregando por ítem (descuenta stock, canal «directo»), marcando la venta como confirmada y la página como Vendida, **una sola vez** aunque se repita. | ✅ | BusinessLedgerTest: confirming_a_linked_sale_marks_the_page_sold_once |
| VEN-04 | Bloquear la confirmación, antes de tocar el stock, si la página vinculada fue rechazada. | ✅ | BusinessLedgerTest: confirming_a_rejected_page_is_blocked_before_stock_changes |
| VEN-05 | Eliminar solo borradores y liberar la página vinculada. | 🟡 | Manual. |
| VEN-06 | «Cliente no continúa»: un borrador pasa a rechazada conservando su historial y encolando el retiro, sin cambiar stock. | ✅ | SaleRejectionTest: rejecting_a_draft_preserves_its_history_and_queues_removal_without_stock_changes |
| VEN-07 | En una venta confirmada, pasar a «cancelación pendiente» y exigir resolución financiera y física por separado (no revierte stock automáticamente). | ✅ | SaleRejectionTest: a_confirmed_sale_requires_separate_financial_and_physical_resolution |
| VEN-08 | Listar las ventas con los borradores primero, el total y la página vinculada. | 🟡 | Manual. |

## Reglas de negocio
- Toda operación de stock lleva clave de idempotencia.
- La cancelación de una venta confirmada requiere conciliación manual.

## Limitaciones y pendientes
Pantalla de configuración de precios inexistente: los precios van fijos en Ventas (PEN-03).
