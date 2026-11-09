# 05 — Publicación, retiro y seguimiento
Última revisión: 2026-11-09 · Estado: ✅ en pruebas / 🟡 sin verificar contra GitHub y Cloudflare reales

## Objetivo
Llevar las páginas activas al repositorio Astro (GitHub), que Cloudflare Pages despliega, y poder retirarlas con evidencia.

## Actores
Administrador; worker de publicación; GitHub; Cloudflare Pages.

## Requisitos

### Exportación y envío
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| PUB-01 | Exportar solo páginas activas y su contenido público al contrato JSON, manteniendo activas las transferencias vacías e ignorando el `place_id` antiguo de la página. | ✅ | PublicPageExportTest |
| PUB-02 | Enviar a GitHub el contrato y los activos sin borrar archivos existentes. | ✅ | PublicPagePublisherTest: manual_publication_sends_the_contract_and_assets_without_deleting_existing_files |
| PUB-03 | No sobrescribir un contrato remoto inválido y no enviar páginas fuera de la selección permitida. | ✅ | PublicPagePublisherTest: invalid_remote_contract_is_not_overwritten, page_outside_publication_selection_cannot_be_sent |
| PUB-04 | Exportar con `clicktap:export-public --output=`. | 🟡 | Cubierto por PublicPageExportTest (lógica); el comando se probó a mano. |

### Cola y worker
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| PUB-10 | Encolar publicaciones desde la web sin hacer peticiones de red y publicar cada página una vez, en orden, desde el worker. | ✅ | PublicationQueueTest: each_page_is_published_once_in_order_without_network_requests_in_the_web_action |
| PUB-11 | Publicar borradores nuevos fuera de la lista blanca heredada sin alterar páginas ya publicadas; no encolar una publicada fuera de la selección. | ✅ | PublicationQueueTest: new_draft_outside_legacy_selection_is_published_without_changing_existing_page, existing_published_page_outside_selection_cannot_be_queued |
| PUB-12 | Mostrar los envíos fallidos sin repetirlos a ciegas y no descartar las demás páginas pendientes. | ✅ | PublicationQueueTest: failed_sends_are_visible_and_not_blindly_repeated, a_failed_page_does_not_discard_other_pending_pages |
| PUB-13 | Conservar una solicitud pendiente heredada al encolar otra, y no publicar una página que quedó inactiva. | ✅ | PublicationQueueTest: a_legacy_pending_request_is_preserved_when_another_page_is_queued, inactive_page_is_not_published_after_it_is_queued |
| PUB-14 | Publicar en lote los borradores seleccionados y rechazar el lote si incluye una ya publicada o una con venta rechazada. | ✅ | PublicacionesSitioTest: selected_drafts_are_activated_and_queued_without_changing_published_pages, bulk_publication_rejects_a_published_page_before_activating_drafts, bulk_publication_rejects_a_page_with_a_rejected_sale |
| PUB-15 | Resolver el estado de todas las páginas visibles con la misma prioridad que el de una sola. | ✅ | PublicationQueueTest: page_statuses_resolve_all_visible_pages_with_the_same_priority_as_single_page_lookup |

### Retiro y reactivación
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| PUB-20 | Retirar una página (`retirada_pendiente → retirando → retirada_enviada → retirada`), reintentar el retiro fallido y reactivar una retirada. | ✅ | PublicationQueueTest: reactivation_replaces_a_withdrawal_that_has_not_started, worker_recovers_a_pending_withdrawal_after_the_request_is_lost |
| PUB-21 | Reemplazar una publicación pendiente por el rechazo, quitando del contrato remoto solo esa página. | ✅ | PublicationQueueTest: rejection_replaces_pending_publication_and_removes_only_that_page_from_remote_contract |
| PUB-22 | Poder retirar una venta rechazada desde Publicaciones. | ✅ | SaleRejectionTest: publications_lists_the_sale_and_can_withdraw_it |

### Seguimiento del despliegue
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| PUB-30 | Confirmar la publicación solo con un despliegue de producción exitoso que coincida y la página pública respondiendo. | ✅ | CloudflareDeploymentTrackerTest: matching_successful_production_deployment_and_live_page_confirm_publication |
| PUB-31 | Confirmar un retiro solo cuando la ruta pública ya no responde y no queda un alias activo. | ✅ | CloudflareDeploymentTrackerTest: withdrawal_remains_unconfirmed_while_public_route_still_responds, withdrawal_is_confirmed_only_when_the_public_route_is_gone, a_still_live_alias_prevents_confirming_withdrawal |
| PUB-32 | Publicar y retirar contra GitHub y Cloudflare reales. | 🟡 | Sin credenciales en local; no verificado. |

### Actividad y conexión
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| PUB-40 | Mostrar el historial de intentos de cada página y un panel central de Actividad; consultar el historial solo si la actividad está abierta. | ✅ | PublicacionesSitioTest: connection_panel_shows_previous_attempts_for_a_page, filters_use_the_local_commercial_projection_and_closed_activity_skips_history |
| PUB-41 | Mostrar el botón de conexión con la cola y el worker, y anunciar los cambios de estado a la lista. | ✅ | PublicacionesSitioTest: connection_button_shows_the_queue_and_worker_signal, connection_button_announces_status_changes_to_the_page_list |
| PUB-42 | Dibujar el mapa de conexiones solo con enlaces de servicios configurados. | ✅ | PublicacionesSitioTest: activity_connection_map_uses_configured_service_links_only |
| PUB-43 | Mantener la selección en el navegador para que clics rápidos no disparen renders en el servidor. | ✅ | PublicacionesSitioTest: selection_is_kept_in_the_browser_so_fast_clicks_do_not_trigger_server_renders |

## Reglas de negocio
- Guardar no es publicar. No se afirma que Cloudflare sirve una página sin evidencia (PUB-30/31).
- La primera publicación de borradores está permitida aunque no estén en `publication_slugs`.

## Limitaciones y pendientes
La cola usa el almacén de archivos (`CLICKTAP_PUBLICATION_STORE`): no es durable ni sirve para varias instancias (PEN-08). Publicación real sin verificar (PUB-32). Animación Hyperframes sin completar.
