# 04 — Logos y medios
Última revisión: 2026-11-09 · Estado: ✅

## Objetivo
Guardar el logo de cada página, derivar su paleta y servirlo de forma resistente aunque Supabase no responda.

## Actores
Administrador; worker de sincronización.

## Requisitos
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| LOG-01 | Guardar logos en el disco de medios configurado (`CLICKTAP_MEDIA_DISK`; Supabase Storage cuando está activo) y usar ese disco para páginas nuevas. | ✅ | SharedCatalogTest: new_pages_use_remote_disk_when_configured |
| LOG-02 | Calcular una paleta recomendada a partir del logo, ignorando el fondo blanco, y permitir restaurarla desde un logo guardado. | ✅ | LogoPaletteTest; AdminPanelTest: recommended_colors_can_be_restored_from_a_saved_logo |
| LOG-03 | No sustituir la paleta elegida al cambiar el logo si está activado «Conservar los colores». | 🟡 | Manual. |
| LOG-04 | Descargar un logo privado una sola vez a una caché local que sobrevive a la pérdida del objeto remoto. | ✅ | LogoCacheTest: private_logo_is_downloaded_once_and_survives_loss_of_the_remote_object |
| LOG-05 | No volver a pedir un logo ausente hasta que se pida un reintento, y permitir reemplazarlo sin perder la referencia antes de guardar. | ✅ | LogoCacheTest: missing_logo_is_not_requested_again_until_a_retry_is_requested, missing_logo_can_be_replaced_without_losing_its_reference_before_save |
| LOG-06 | Recuperar un logo ausente desde la copia publicada (rama publicada) de solo lectura, también para páginas antiguas. | ✅ | LogoCacheTest: missing_supabase_logo_uses_the_read_only_published_copy, published_branch_is_used_when_the_recorded_commit_has_no_logo, legacy_page_can_recover_its_logo_from_the_published_branch |
| LOG-07 | Servir `/media/page-logo/{pagina}/{version}` solo a usuarios activos y rechazar versiones viejas; si no puede escribir la caché, redirigir a una URL temporal de Supabase. | ✅ | LogoCacheTest: logo_route_rejects_old_versions_and_inactive_users, logo_route_uses_a_short_lived_supabase_url_if_local_cache_cannot_be_written |
| LOG-08 | Calentar la caché con `clicktap:cache-supabase-logos` informando las imágenes ausentes sin borrar las ya cacheadas, y hacerlo tras cada sincronización exitosa. | ✅ | LogoCacheTest: warm_command_reports_missing_images_without_erasing_cached_ones, catalog_worker_warms_images_after_a_successful_sync |
| LOG-09 | Migrar logos locales a Supabase verificando la copia y sin borrar el original ni reemplazar un archivo remoto distinto. | ✅ | SharedCatalogTest: logo_copy_is_verified_and_does_not_delete_local_original, logo_migration_refuses_to_replace_a_different_remote_file |
| LOG-10 | Cargar el logo desde la investigación automática de un negocio. | ✅ | Ver INV-40 a INV-43 |

## Reglas de negocio
- La copia local de un logo nunca se borra automáticamente al migrar.
- Formatos de imagen aceptados al importar de internet: JPG, PNG, WEBP y GIF de al menos 32 px.

## Limitaciones y pendientes
Sin pruebas automáticas del interruptor «Conservar los colores».
