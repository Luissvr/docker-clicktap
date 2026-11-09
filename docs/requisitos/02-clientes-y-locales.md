# 02 — Clientes y locales
Última revisión: 2026-11-09 · Estado: ✅

## Objetivo
Registrar las personas y negocios a los que se les vende una tarjeta y asociarlos a una página.

## Actores
Administrador.

## Requisitos
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| CLI-01 | Registrar un cliente con nombre (obligatorio), persona de contacto, teléfono, correo y notas internas. | ✅ | LocalManualEntryTest: customer_creation_validates_manual_fields |
| CLI-02 | Buscar clientes por nombre, contacto o teléfono y filtrar por datos de contacto (completo/incompleto) y por locales asociados; los filtros se combinan. | ✅ | ResponsiveContentTablesTest: client_filters_combine_with_phone_search |
| CLI-03 | Cambiar el botón de la fila a «Añadir contacto» cuando faltan persona o teléfono, y a «Editar» cuando están completos. | ✅ | ResponsiveContentTablesTest: clients_offer_creation_and_contact_actions_in_the_compact_layout |
| CLI-04 | Mostrar cuántos locales tiene cada cliente. | 🟡 | Manual. |
| CLI-05 | Crear un local con cliente (que puede crearse en el mismo formulario), nombre interno, dirección operativa y notas. | ✅ | LocalManualEntryTest: administrator_can_type_a_new_customer_and_address_without_leaving_the_local_form |
| CLI-06 | Asignar a un local existente un cliente y una dirección manual. | ✅ | LocalManualEntryTest: existing_local_can_be_assigned_a_customer_and_a_manual_address |
| CLI-07 | Listar locales con su cliente («Sin asignar» si no tiene), la dirección completa («Sin completar» si falta) y la página asociada. | ✅ | ResponsiveContentTablesTest: locals_show_full_addresses_and_missing_relations |
| CLI-08 | Autorizar la creación de clientes según la política; un usuario sin permiso no puede crearlos. | ✅ | LocalManualEntryTest: customer_creation_respects_authorization |
| CLI-09 | Impedir que un formulario antiguo de cliente o local sobrescriba el cambio de otro socio (control de revisión). | ✅ | SharedCatalogTest: stale_client_form_cannot_overwrite_another_partner, stale_local_form_cannot_overwrite_another_partner |

## Reglas de negocio
- El cliente es **opcional** para crear páginas (páginas demo y de ruta).
- Un local tiene una sola página (`pagina`); la restricción no está comprobada en la base de datos.

## Limitaciones y pendientes
Unicidad local → página sin restricción en BD; portal del cliente (PEN-01).
