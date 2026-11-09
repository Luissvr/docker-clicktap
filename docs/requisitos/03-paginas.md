# 03 — Páginas (editor, estado comercial y papelera)
Última revisión: 2026-11-09 · Estado: ✅

## Objetivo
Crear y mantener la tarjeta digital pública de cada negocio: contenido, enlaces, apariencia, vista previa, estado comercial y ciclo de vida (borrador, papelera).

## Actores
Administrador.

## Requisitos

### Creación y contenido
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| PAG-01 | Crear una página con cliente, local, página y enlaces en una sola operación, y deshacerla entera si falla (ruta ocupada). | ✅ | CreatePageActionTest: creates_customer_location_page_and_links_together, rolls_back_creation_when_the_route_is_taken |
| PAG-02 | Crear páginas demo sin cliente, inactivas. | ✅ | AdminPanelTest: administrator_can_create_a_demo_page_without_a_customer; CreatePageActionTest: creates_an_inactive_demo_page_without_a_customer |
| PAG-03 | Guardar la página nueva como **borrador**: crear no publica. | ✅ | PublicacionesSitioTest: selected_drafts_are_activated_and_queued_without_changing_published_pages |
| PAG-04 | Aceptar una descripción de hasta 150 palabras y rechazar 151. | ✅ | CreatePageActionTest: description_accepts_150_words_and_rejects_151; AdminPanelTest: page_form_rejects_more_than_150_description_words |
| PAG-05 | No permitir cambiar la ruta (`slug`) tras crear la página, ni usar rutas reservadas o alias antiguos. | ✅ | PageContentProtectionTest: slug_cannot_change_after_creation, reserved_and_legacy_alias_routes_cannot_be_used |
| PAG-06 | Rechazar la creación o edición a un administrador inactivo. | ✅ | CreatePageActionTest: inactive_administrator_cannot_create_a_page; UpdatePageActionTest: inactive_administrator_cannot_update_a_page |
| PAG-07 | Reemplazar los enlaces al actualizar y deshacer todo si hay posiciones duplicadas. | ✅ | UpdatePageActionTest: updates_current_content_and_replaces_links, duplicate_link_positions_roll_back_the_save |
| PAG-08 | Impedir que un formulario desactualizado sobrescriba contenido o enlaces de otro socio; permitir guardar dos veces el mismo formulario. | ✅ | SharedCatalogTest: stale_page_form_cannot_overwrite_content_or_links, same_form_can_save_twice_after_successful_revision_update |
| PAG-09 | Permitir rellenar el formulario con la investigación automática de un negocio. | ✅ | Ver [12-investigacion-de-negocios.md](12-investigacion-de-negocios.md) |

### Enlaces
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| PAG-20 | Ofrecer los tipos de enlace WhatsApp, Instagram, TikTok, Facebook, web, reseñas de Google, ubicación, transferencia (con banco) y otro. | ✅ | AdminPanelTest: add_link_button_adds_an_editable_link_on_create_page |
| PAG-21 | Crear el enlace de reseñas a partir de su propio `place_id` y permitir editarlo sin tocar el de la página. | ✅ | AdminPanelTest: review_link_is_created_from_its_own_place_id, existing_review_place_id_can_be_edited_without_the_page_place_id |
| PAG-22 | Permitir enlaces de transferencia sin destino, activos, que se muestran como botón; el destino o la etiqueta pueden quedar vacíos. | ✅ | AdminPanelTest: empty_transfer_can_be_saved_active_and_renders_as_a_button, preview_shows_an_empty_link_without_a_broken_destination |
| PAG-23 | Mostrar un botón de copiar cuando el enlace tiene texto copiable (datos de transferencia). | ✅ | AdminPanelTest: preview_renders_a_copy_button_when_configured |
| PAG-24 | Reordenar enlaces (subir, bajar, modo ordenar con un enlace a la vez) y borrar varios a la vez. | ✅ | AdminPanelTest: order_mode_selects_one_link_at_a_time_and_restores_collapse_arrows, create_page_can_delete_multiple_selected_links_and_collapse_items, edit_page_can_delete_one_selected_link |
| PAG-25 | Usar el nombre del enlace en la vista previa cuando la etiqueta del botón está vacía. | ✅ | AdminPanelTest: preview_falls_back_to_link_name_when_button_label_is_empty, page_can_be_saved_with_chosen_typography_and_link_name |

### Apariencia y vista previa
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| PAG-30 | Aplicar tema de fondo, tipografía, colores y disposición del logo (original, esquinas suaves, circular) y ancho del logo. | ✅ | AdminPanelTest: preview_applies_the_selected_background_theme, standalone_preview_uses_the_selected_layout_and_keeps_the_tagline |
| PAG-31 | Cambiar entre modos de apariencia reconstruyendo el diseño, conservando la frase bajo el nombre sin guardar. | ✅ | AdminPanelTest: switching_appearance_rebuilds_the_layout_and_keeps_the_tagline_without_saving, both_appearance_modes_show_description_and_only_explicit_review_links |
| PAG-32 | Mostrar vista previa en vivo en el editor y abrir la vista previa final (también móvil) en otra pestaña usando los cambios sin guardar, sin validar ni guardar. | ✅ | AdminPanelTest: final_preview_can_open_during_creation_without_saving_or_validating_the_form, final_preview_uses_unsaved_edits_without_changing_the_saved_page, preview_can_render_as_a_mobile_device |
| PAG-33 | Compilar la vista previa solo cuando se pide, también al editar. | ✅ | AdminPanelTest: page_preview_only_compiles_when_requested, edit_page_preview_loads_the_saved_page_only_when_requested |
| PAG-34 | Justificar descripciones sin espacios por caracteres en ambas vistas. | ✅ | AdminPanelTest: unbroken_description_uses_character_justification_in_both_previews |
| PAG-35 | Servir `/preview/{slug}` solo a usuarios autenticados con permiso. | ✅ | SharedCatalogTest: guest_and_inactive_account_cannot_read_private_catalog_or_preview |

### Listado
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| PAG-40 | Listar las páginas de a 50, con detalle y acciones aun con contenido largo, y ordenarlas por nombre desde la barra. | ✅ | ResponsiveContentTablesTest: pages_start_with_fifty_records_per_page, pages_keep_all_details_and_actions_with_long_content, pages_can_be_ordered_by_name_from_the_toolbar |
| PAG-41 | Enlazar al sitio público solo cuando el envío ya se realizó. | ✅ | ResponsiveContentTablesTest: pages_link_to_the_public_site_only_after_publication_is_sent |
| PAG-42 | Buscar y filtrar tarjetas por estado y contar por búsqueda y álbum; limpiar filtros; ordenar y paginar antes de renderizar. | ✅ | PublicacionesSitioTest: search_and_status_cards_replace_the_old_filters…, sort_and_page_size_are_applied_before_rendering_cards, commercial_counts_follow_search_and_album, clear_filters_restores_defaults |

### Estado comercial
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| PAG-50 | Clasificar cada página como Por vender, Vendida o Rechazada y separarlas en tarjetas. | ✅ | PublicacionesSitioTest: cards_separate_sold_for_sale_and_rejected_pages |
| PAG-51 | Iniciar una venta única vinculada desde una página Por vender. | ✅ | PublicacionesSitioTest: mark_sold_starts_one_linked_sale_for_a_page |
| PAG-52 | Rechazar una página («el cliente no continúa») una sola vez con motivo auditado, desactivarla y encolar su retiro. | ✅ | PublicacionesSitioTest: page_without_sale_can_be_rejected_once_with_an_audited_reason |
| PAG-53 | Rechazar varias a la vez con un único motivo (límite por lote), omitiendo las vendidas y las de papelera. | ✅ | PublicacionesSitioTest: selected_pages_are_rejected_together_with_one_audited_reason, bulk_rejection_skips_sold_and_trashed_pages_and_keeps_the_rest, bulk_rejection_requires_a_reason_and_limits_the_batch |
| PAG-54 | No permitir reactivar editando una página rechazada ni retirar desde la tarjeta una vendida. | ✅ | PublicacionesSitioTest: rejected_sale_cannot_be_reactivated_by_editing_the_page, sold_page_cannot_be_unpublished_from_the_commercial_card |
| PAG-55 | Mantener la lista disponible con la proyección local si Supabase falla. | ✅ | PublicacionesSitioTest: remote_sale_outage_does_not_block_listing_with_local_projection |

### Papelera y borrado
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| PAG-60 | Enviar a la papelera una o varias páginas, publicadas o borradores; una publicada espera el retiro verificado. | ✅ | PageTrashTest: published_page_moves_to_trash_and_waits_for_verified_withdrawal, bulk_trash_accepts_published_pages_and_drafts |
| PAG-61 | Restaurar una página nunca publicada sin nuevo despliegue; una página en papelera no puede activarse directamente. | ✅ | PageTrashTest: never_published_page_can_be_restored_without_a_deployment, page_in_trash_cannot_be_activated_directly |
| PAG-62 | Eliminar definitivamente borradores (varios a la vez) sin tocar los publicados; vaciar la papelera solo quita borradores nunca publicados. | ✅ | PageTrashTest: multiple_drafts_can_be_deleted_directly_without_touching_a_published_page, empty_trash_only_removes_never_published_drafts, bulk_direct_delete_purges_drafts_and_trashes_published_pages |
| PAG-63 | Guardar un respaldo (contenido y logo) antes del borrado definitivo y permitir descargarlo. | ✅ | PageTrashTest: backup_contains_content_and_logo_before_permanent_deletion |
| PAG-64 | Podar la papelera vieja a diario (03:15) solo cuando el acceso público ya fue retirado. | ✅ | PageTrashTest: old_trash_is_pruned_only_after_public_access_is_removed |
| PAG-65 | Mover un borrador a la papelera desde el editor cancelando su publicación encolada; no ofrecer el borrado una vez enviado. | ✅ | EditPaginaDraftDeletionTest: editor_moves_an_unpublished_draft_to_trash_and_cancels_its_queued_publication, editor_does_not_offer_draft_deletion_after_a_page_was_sent, running_publication_is_withdrawn_if_its_draft_is_removed |

## Reglas de negocio
- La ruta (`slug`) es permanente: las tarjetas NFC/QR ya entregadas dependen de ella.
- Guardar no es publicar; crear no es publicar.
- Una página rechazada se desactiva y no vuelve a publicarse sin intervención manual.

## Limitaciones y pendientes
Versionado y aprobación de páginas (PEN-06); asignación de placas NFC (PEN-07). Ver también [04-logos-y-medios.md](04-logos-y-medios.md) y [05-publicacion.md](05-publicacion.md).
