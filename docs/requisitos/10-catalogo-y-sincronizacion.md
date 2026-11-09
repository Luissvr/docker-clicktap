# 10 — Catálogo compartido y sincronización
Última revisión: 2026-11-09 · Estado: ✅

## Objetivo
Compartir clientes, locales y páginas entre socios con Supabase como origen de escritura y una copia local PostgreSQL para lectura rápida.

## Actores
Administrador; worker de sincronización (`clicktap:sync-local-catalog`, cada 20 min).

## Requisitos
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| CAT-01 | Leer listas y formularios desde la copia local. | ✅ | CatalogConnectionTest: lists_and_live_form_hydration_use_the_local_copy |
| CAT-02 | Confirmar primero cada alta o edición en el remoto y recién después reflejarla en la copia local. | ✅ | CatalogConnectionTest: create_and_edit_are_committed_remotely_then_visible_locally |
| CAT-03 | Dejar la creación de páginas y los cambios de enlaces en ambas bases. | ✅ | CatalogConnectionTest: page_creation_and_link_changes_reach_both_databases |
| CAT-04 | Si la transacción remota falla, no cambiar la copia local; si falla solo el refresco local, no informar el guardado remoto como fallido. | ✅ | CatalogConnectionTest: failed_remote_transaction_cannot_change_the_local_copy, local_refresh_failure_does_not_report_the_remote_save_as_failed |
| CAT-05 | Conservar el borrador y los datos remotos ante un conflicto de revisión, y con Supabase caído mantener el formulario y los datos locales. | ✅ | CatalogConnectionTest: remote_revision_conflict_preserves_the_draft_and_remote_data, unavailable_remote_save_keeps_the_form_and_local_data |
| CAT-06 | No sobrescribir con la sincronización un formulario con cambios sin guardar, y comprobar la revisión remota en el siguiente guardado. | ✅ | CatalogConnectionTest: sync_does_not_overwrite_an_unsaved_form_and_next_save_checks_remote_revision |
| CAT-07 | Copiar el catálogo completo conservando tipos y excluyendo credenciales; reconciliar actualizaciones y borrados en sincronizaciones repetidas. | ✅ | LocalCatalogSyncTest: full_copy_preserves_types_and_excludes_credentials, repeat_sync_reconciles_updates_and_deletions |
| CAT-08 | Revertir todas las tablas y la hora de éxito si la copia se interrumpe; conservar la copia previa si falta una tabla de origen; rechazar un esquema local no identificado y la sincronización concurrente. | ✅ | LocalCatalogSyncTest: interrupted_copy_rolls_back_all_tables_and_success_time, missing_source_table_keeps_previous_copy, refuses_an_unidentified_existing_schema, concurrent_sync_is_rejected_without_changing_the_copy |
| CAT-09 | Permitir a un usuario activo pedir una sincronización sin ejecutarla dentro de la petición; negarla a invitados e inactivos. | ✅ | CatalogSyncStatusTest: active_user_can_request_sync_without_running_it_in_the_form_request, guest_and_inactive_users_cannot_request_sync |
| CAT-10 | Sincronizar al arrancar el worker, atender la solicitud manual y anunciar el término a las listas. | ✅ | CatalogSyncStatusTest: worker_syncs_on_startup_and_consumes_manual_request, successful_sync_announces_completion_to_lists |
| CAT-11 | Compartir clientes y locales entre socios registrando quién hizo cada cambio. | ✅ | SharedCatalogTest: partners_share_clients_and_locales_and_changes_record_the_actor |

## Reglas de negocio
- Supabase escribe; la copia local solo lee. Las decisiones de stock nunca salen de la copia.
- Intervalo de sincronización: 1200 s (`sync_interval_seconds`).

## Limitaciones y pendientes
Aislamiento de pruebas: forzar `AUTH_DB_CONNECTION=sqlite` en `phpunit.xml` (PEN-11). Sincronización parcial de la tabla pivote de álbumes: ver [06-albumes.md](06-albumes.md).
