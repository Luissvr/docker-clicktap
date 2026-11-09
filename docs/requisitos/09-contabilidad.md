# 09 — Contabilidad
Última revisión: 2026-11-09 · Estado: ✅

## Objetivo
Llevar la caja del negocio: ingresos, egresos, cobros y pagos a proveedores, con finanzas por rango de fechas.

## Actores
Administrador. Fachada: `BusinessLedger`.

## Requisitos
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| CON-01 | Registrar movimientos de caja (ingreso/egreso, categoría, monto, fecha, descripción, comprobante), por ejemplo el aporte inicial o la publicidad. | 🟡 | Cubierto indirectamente en BusinessLedgerTest; sin prueba específica de movimientos manuales. |
| CON-02 | Registrar el cobro (abono) de un pedido sin mezclarlo con el stock. | ✅ | BusinessLedgerTest: reservation_delivery_payment_and_purchase_payment_keep_stock_and_cash_separate |
| CON-03 | Pagar una compra a proveedor y rechazar el sobrepago. | ✅ | StockPurchasesTest: unknown_cost_is_not_reported_as_free_and_overpayment_is_rejected |
| CON-04 | Reembolsar una reserva. | 🟡 | Manual. |
| CON-05 | Consultar las finanzas por rango de fechas (`finances(from, to)`). | 🟡 | Manual. |
| CON-06 | Abrir la pantalla de Contabilidad en el panel. | ✅ | BusinessLedgerTest: administrator_can_open_stock_and_accounting_pages |
| CON-07 | Conciliación contable formal y reportes exportables. | ❌ | Ver PEN-10. |

## Reglas de negocio
- Los costos no se inventan: un costo desconocido se muestra como tal.
- Las cifras iniciales de caja y stock están por conciliar.

## Limitaciones y pendientes
Conciliación formal y reportes (PEN-10); pruebas específicas de movimientos manuales, reembolso y rango de fechas.
