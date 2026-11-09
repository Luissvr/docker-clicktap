# 11 — Prospección de rutas
Última revisión: 2026-11-09 · Estado: ✅

## Objetivo
Recopilar datos públicos de negocios de una ruta desde Google Maps, verificar su identidad y crear borradores de página por lote.

## Actores
Administrador de sistemas (consola): `clicktap:research-route`, `clicktap:create-route-pages`.

## Requisitos
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| RUT-01 | Capturar el negocio que coincide en Google Maps y extraer el enlace social real de su ficha. | ✅ | RouteProspectsTest: google_research_captures_the_matching_business_and_unwraps_its_social_link |
| RUT-02 | Invalidar un resultado verificado si cambian los datos de entrada y refrescar las investigaciones antiguas sin huella. | ✅ | RouteProspectsTest: changed_inputs_invalidate_a_previously_matching_google_result, legacy_research_without_an_input_fingerprint_is_refreshed |
| RUT-03 | No confundir negocios: ignorar palabras genéricas, tolerar tildes y detalles extra del nombre, y **no aceptar** una dirección sola, otro negocio u otra sucursal. | ✅ | RouteProspectsTest: generic_description_words_do_not_replace_the_distinctive_business_name, extra_google_name_details_and_accents_preserve_verified_business_matches, an_address_only_a_different_business_or_a_different_branch_is_never_accepted |
| RUT-04 | Registrar y reportar un fallo de red sin inventar datos del negocio. | ✅ | RouteProspectsTest: network_failure_is_recorded_and_reported_without_inventing_business_data |
| RUT-05 | No crear botones de reseñas a partir de candidatos que no coinciden. | ✅ | RouteProspectsTest: unmatched_google_candidates_do_not_produce_review_buttons |
| RUT-06 | Crear borradores por lote desde el manifiesto (`--dry-run`, `--previews`, `--actor`) usando la plantilla pedida, sin copiar contenido de clientes. | ✅ | RouteProspectsTest: creation_uses_the_requested_batch_and_template_without_copying_client_content |

## Reglas de negocio
- Las rutas y su manifiesto están en `database/route_prospects_2026_10_28.json`.
- Se guardan las fuentes y los pendientes de cada negocio.

## Limitaciones y pendientes
La investigación interactiva de un solo negocio, desde el panel, es otro módulo: [12-investigacion-de-negocios.md](12-investigacion-de-negocios.md). El botón para procesar rutas por lote desde el panel sigue pendiente (fase 5 del plan de investigación).
