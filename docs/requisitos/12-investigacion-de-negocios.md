# 12 — Investigación automática de un negocio
Última revisión: 2026-11-09 (+ vista previa de perfiles y revisión de enlaces de la web; commits `73672ea`, `e701760`, `2e5fe45`, `8784fa5`, `b2f569b`) · Estado: ✅ con limitaciones (ver al final)

## Objetivo
En «Crear página», el administrador escribe el nombre de un negocio, elige el correcto entre los resultados de Google y el sistema investiga en segundo plano sus enlaces (ficha, web, redes, WhatsApp, logo). Los datos se ofrecen con su origen y nivel de confianza y se vuelcan al formulario solo si el administrador los acepta. La página se guarda como borrador; nada se publica solo.

Diseño y decisiones: `docs/PLAN_INVESTIGACION_NEGOCIOS.md`.

## Actores
Administrador (usuario activo). Procesos: `research-worker` (servicio Docker), Google Maps (sin clave), Brave Search (clave opcional), OpenRouter (clave opcional).

## Requisitos

### Búsqueda y elección
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| INV-01 | Mostrar el cuadro «Buscar negocio en internet» (nombre y ciudad, con ciudad por defecto configurable) solo al **crear** una página, no al editarla. | ✅ | BusinessResearchPanelTest: create_page_shows_the_name_search_box, search_box_is_not_offered_when_editing |
| INV-02 | Rechazar nombres de menos de 3 o más de 120 caracteres sin crear la tarea. | ✅ | BusinessResearchPanelTest: a_name_that_is_too_short_is_rejected_without_queueing |
| INV-03 | Crear la tarea sin bloquear la pantalla y mostrar «Buscando…» con actualización automática. | ✅ | BusinessResearchPanelTest: typing_a_name_queues_the_search_and_shows_progress |
| INV-04 | Limitar a 20 búsquedas por hora y usuario. | ✅ | BusinessResearchPanelTest: searches_are_limited_per_user_per_hour |
| INV-05 | Listar los negocios que Google Maps devuelve (hasta 8) con nombre, dirección, categoría, valoración y enlace a Maps, y dejar elegir con «Es este» o descartar con «Ninguno, buscar de nuevo». | ✅ | BusinessResearchQueueTest: search_lists_every_business_with_that_name; BusinessResearchPanelTest: the_user_sees_every_match_and_chooses_the_right_one |
| INV-06 | Reintentar sin la ciudad si la búsqueda con ciudad no devuelve nada. | ✅ | BusinessResearchQueueTest: search_retries_without_the_city_when_google_finds_nothing_with_it |
| INV-07 | Informar «Google no encontró negocios…» y permitir buscar de nuevo; informar con un mensaje claro si Google falla. | ✅ | BusinessResearchPanelTest: no_matches_show_a_message_and_allow_searching_again; BusinessResearchQueueTest: google_failure_marks_the_task_as_failed_with_a_clear_message |
| INV-08 | Listar **todos los negocios homónimos** de una ciudad. | 🟡 | Limitación: Maps embebido devuelve un solo candidato para nombres concretos y ninguno para genéricos. Requiere Google Places (ver PEN-04). |

### Investigación por pasos
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| INV-10 | Tras la elección, investigar en segundo plano seis pasos con su estado visible (pendiente, buscando, encontrado, no encontrado, omitido, con error): ficha de Google, web, Instagram, Facebook, TikTok y WhatsApp. | ✅ | BusinessResearchQueueTest: choosing_a_business_investigates_each_link_in_the_background |
| INV-11 | Tomar de la ficha: nombre, dirección, rubro, teléfono, horario, `place_id` (reseñas), enlace de ubicación y, si la ficha apunta a una web o red, ese enlace. | ✅ | BusinessResearchQueueTest: choosing_a_business…, a_google_listing_that_points_to_instagram_is_used_directly |
| INV-12 | Presentar el horario agrupando días consecutivos con el mismo horario (`Lunes-Sábado 8am - 7pm`), con varios turnos separados por « / » y conservando el texto que no pueda interpretar. | ✅ | OpeningHoursFormatterTest; BusinessResearchQueueTest: opening_hours_are_grouped_by_consecutive_days_with_the_same_schedule |
| INV-13 | Omitir el paso de web si el negocio no tiene una en su ficha, y seguir con los demás pasos si un paso falla. | ✅ | BusinessResearchQueueTest: a_business_without_a_website_skips_that_step…, a_failing_website_does_not_stop_the_other_steps |
| INV-14 | Permitir cancelar una tarea en curso; una tarea cancelada no se procesa y una elección exige un índice válido. | ✅ | BusinessResearchQueueTest: a_cancelled_task_is_not_processed_and_a_choice_needs_a_valid_index |
| INV-15 | Avisar que el proceso en segundo plano no responde si el `research-worker` no da señal pasados 15 segundos. | 🟡 | BusinessResearchQueueTest: work_reports_when_nothing_is_pending_and_registers_the_worker_heartbeat (latido; el aviso en pantalla es manual) |

### Lectura de la web del negocio
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| INV-20 | Leer de la web, de forma determinista, teléfonos, enlaces de WhatsApp, redes sociales (Instagram, Facebook, TikTok), descripción y datos estructurados (JSON-LD), ignorando enlaces de compartir y scripts. | ✅ | WebsiteResearchTest: reader_extracts_contact_data_and_ignores_share_links_and_scripts |
| INV-25 | Descartar los enlaces de redes de la web que pertenecen al proveedor de la plantilla o a la propia red (p. ej. Ancora Themes) y anotarlos como descartados; rebajar a «Sugerido» una red de la web cuyo usuario no se parece al nombre del negocio; descartar un WhatsApp de la web que no sea un móvil chileno (+56 9 y 8 dígitos). | ✅ | BusinessResearchQueueTest: links_to_the_website_template_author_are_discarded_and_the_real_profiles_are_searched, a_website_profile_that_does_not_resemble_the_business_is_only_a_suggestion |
| INV-21 | Bloquear direcciones internas o no web, revalidar cada redirección (máx. 3), usar solo puertos 80/443, rechazar credenciales en la URL y limitar la descarga a 2 MB y 10 s (protección SSRF). | ✅ | WebsiteResearchTest: fetcher_blocks_internal_or_non_web_addresses, fetcher_revalidates_redirects_and_blocks_internal_targets, fetcher_follows_public_redirect_and_returns_body |
| INV-22 | Si hay clave de OpenRouter, ordenar con IA los datos de la web y conservar **solo lo que aparece en la página** (teléfonos presentes en el texto, redes enlazadas); lo demás se descarta o queda como «Sugerido». | ✅ | WebsiteResearchTest: extractor_keeps_only_data_found_on_the_page, extractor_retries_with_json_object_when_no_provider_supports_the_schema, rate_limit_fails_once_without_retrying |
| INV-23 | Funcionar sin IA (comando `clicktap:research-website --sin-ia`). | ✅ | WebsiteResearchTest: command_reports_page_data_without_ai_and_without_saving, command_fails_clearly_for_internal_address |
| INV-24 | Probar el extractor de IA con una clave real. | 🟡 | Solo con respuestas simuladas; nunca ejecutado con un modelo real. |

### Redes sociales
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| INV-30 | Buscar en Brave Search, con una consulta por red, el perfil que falte; aceptar solo URL de perfil (no publicaciones, reels ni enlaces de compartir) cuyo título, descripción o ruta mencione el negocio, y mostrarla siempre como «Sugerido». | ✅ | BusinessResearchQueueTest: brave_search_suggests_only_real_profiles_that_mention_the_business |
| INV-37 | Cuando una red no está confirmada (sin dato, sugerida por la web o sugerida por Brave), consultar Brave y mostrar hasta 3 **posibles perfiles** con vista previa (título, descripción, miniatura si Brave la entrega y enlace para abrirlo) y un botón «Usar este» que lo deja como «Verificado» con origen «Confirmado por ti». El servidor solo acepta un índice de la lista guardada, nunca una URL enviada desde el navegador. | ✅ | BusinessResearchPanelTest: search_results_are_offered_with_a_preview_and_can_be_confirmed; BusinessResearchQueueTest: links_to_the_website_template_author_are_discarded_and_the_real_profiles_are_searched |
| INV-31 | Informar en cada paso si Brave falla (clave, cuota, límite) sin detener la investigación, y explicar que la búsqueda no está configurada si falta la clave. | ✅ | BusinessResearchQueueTest: brave_failures_are_reported_in_each_step…, without_a_brave_key_social_steps_explain_that_the_search_is_not_configured |
| INV-32 | Ofrecer un enlace de búsqueda manual en Google en cada red no encontrada. | ✅ | Manual |
| INV-33 | Ofrecer, en Instagram, Facebook y TikTok no encontrados o con error, un campo para **pegar el enlace del perfil o su @usuario**, validarlo como perfil real y guardarlo como «Verificado» con origen «Ingresado por ti». Aceptar enlace completo, sin `https` o solo el usuario; rechazar publicaciones, reels, videos, enlaces de compartir, perfiles de otra red y texto con espacios. | ✅ | SocialProfileUrlTest; BusinessResearchPanelTest: a_missing_network_offers_a_field_to_paste_the_profile, a_pasted_profile_is_validated_saved_and_applied_to_the_form, a_username_alone_is_enough, a_post_or_another_network_is_rejected_with_a_clear_message |
| INV-34 | No ofrecer el campo de pegado para WhatsApp ni para pasos distintos de las tres redes. | ✅ | BusinessResearchPanelTest: the_paste_field_is_not_offered_for_other_steps_or_unknown_networks |
| INV-35 | Sugerir WhatsApp a partir del teléfono si es móvil chileno (+569), marcado como «Sugerido». | ✅ | BusinessResearchQueueTest: a_business_without_a_website_skips_that_step_and_suggests_whatsapp_for_mobile_numbers |
| INV-36 | Encontrar de forma automática las redes de negocios pequeños. | 🟡 | Brave no indexa algunos perfiles (caso «7E Barber Shop») y a veces devuelve homónimos de otra ciudad (caso «Casa Minga»); por eso se muestra la vista previa (INV-37) y se puede pegar el enlace (INV-33). Alternativa: Serper/SerpApi (PEN-05). |

### Logo
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| INV-40 | Proponer el logo desde la web del negocio con prioridad: `logo` de datos estructurados, `apple-touch-icon` más grande, `og:image`; mostrarlo «Sugerido», sin marcar y con miniatura. | ✅ | BusinessResearchPanelTest: the_logo_found_on_the_website_is_downloaded_and_loaded_into_the_form |
| INV-41 | Al aplicar, descargar el logo con la protección SSRF, aceptar solo JPG/PNG/WEBP/GIF de al menos 32 px, guardarlo en `logos/` del disco de medios y cargarlo en el campo Logo, recalculando la paleta salvo que esté activado «Conservar los colores». | ✅ | BusinessResearchPanelTest: the_logo_found_on_the_website_is_downloaded_and_loaded_into_the_form |
| INV-42 | Si el archivo no es una imagen válida, avisar que se suba a mano y aplicar igualmente el resto de los datos, sin guardar archivos. | ✅ | BusinessResearchPanelTest: a_logo_that_is_not_an_image_is_reported_without_breaking_the_rest |
| INV-43 | Obtener el logo cuando el negocio no tiene web. | ❌ | La ficha de Google no trae logo y las fotos de Instagram/Facebook requieren sesión. Queda como pendiente manual. |

### Resultado y seguridad
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| INV-50 | Mostrar cada dato con su etiqueta **Verificado** o **Sugerido** y su origen (ficha de Google, web del negocio, IA comprobada en la web, búsqueda web, teléfono móvil, ingresado por ti). | ✅ | BusinessResearchPanelTest: applying_the_result_fills_fields_review_and_links |
| INV-51 | Preseleccionar solo los datos «Verificados» que el formulario aún no tiene; marcar «Reemplaza el valor actual» cuando el formulario ya tiene valor. | ✅ | BusinessResearchPanelTest: existing_values_are_kept_unless_the_user_selects_them |
| INV-52 | Al aplicar, volcar al formulario solo lo seleccionado (nombre y ruta, rubro, dirección, teléfono, horario, descripción de hasta 150 palabras, logo, reseñas con `place_id`, ubicación, WhatsApp, Instagram, Facebook, TikTok, web), evitando enlaces duplicados. | ✅ | BusinessResearchPanelTest: applying_the_result_fills_fields_review_and_links |
| INV-53 | Dejar la página como **borrador**; la investigación nunca publica. | ✅ | Regla de negocio; ver PAG |
| INV-54 | Mostrar la tarea solo a quien la creó e impedir manipular su identificador desde el navegador. | ✅ | BusinessResearchPanelTest: another_administrator_cannot_see_someone_elses_search, the_search_state_cannot_be_tampered_with_from_the_browser |
| INV-55 | Descartar los datos que la IA no pueda comprobar en la web y listarlos como descartados. | ✅ | WebsiteResearchTest: extractor_keeps_only_data_found_on_the_page |

## Reglas de negocio
- Los datos del negocio se obtienen de fuentes públicas oficiales: Google Maps embebido, páginas web públicas y las API de Brave/OpenRouter. **No se lee el buscador normal de Google ni se imita un navegador** para evitar controles anti-robots.
- Un dato que no pueda comprobarse en su fuente nunca se muestra como «Verificado».
- Las tareas viven 24 horas en el almacén de caché de publicaciones (sin tablas ni migraciones).
- Operación: el servicio `research-worker` debe estar levantado, siempre con los dos archivos de Compose (`docker-compose.yml` + `docker-compose.supabase.yml`).

## Configuración
`OPENROUTER_API_KEY`, `OPENROUTER_MODEL` (por defecto `openrouter/free`), `BRAVE_SEARCH_API_KEY`, `CLICKTAP_RESEARCH_CITY` (por defecto Concepción). Sin claves el flujo funciona con Google Maps y la lectura determinista de la web.

## Limitaciones y pendientes
Homónimos (PEN-04), segunda fuente de redes (PEN-05), salto a la pestaña «Enlaces» tras aplicar, latencia de 10–14 s en la lista de candidatos, extractor de IA sin probar con modelo real (INV-24), términos de almacenamiento y crédito gratuito de Brave sin confirmar. Detalle en [14-pendiente.md](14-pendiente.md).
