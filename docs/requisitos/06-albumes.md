# 06 — Álbumes
Última revisión: 2026-11-09 · Estado: ✅ / 🟡 sincronización

## Objetivo
Agrupar páginas (por ejemplo, una ruta de visitas) para filtrarlas, sin cambiar su publicación.

## Actores
Administrador.

## Requisitos
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| ALB-01 | Crear un álbum (nombre, fecha de ruta), editarlo y eliminarlo. | 🟡 | Manual (crear/editar/eliminar no tienen prueba propia). |
| ALB-02 | Agrupar las páginas seleccionadas en un álbum sin cambiar su publicación. | ✅ | PublicacionesSitioTest: route_album_groups_selected_pages_without_changing_publication |
| ALB-03 | Quitar una página de un álbum y filtrar el listado por álbum. | 🟡 | Manual; los conteos por álbum sí están cubiertos (commercial_counts_follow_search_and_album). |
| ALB-04 | Reconciliar las asignaciones y eliminaciones de álbumes en la copia local sin borrar páginas. | ✅ | LocalCatalogSyncTest: album_partial_sync_reconciles_assignment_and_deletion_without_deleting_pages |

## Limitaciones y pendientes
Riesgo señalado el 2026-11-09: `LocalCatalogSync::syncRecord()` no admite la tabla pivote `album_pagina` en la sincronización parcial, y falta verificar las migraciones en local y Supabase. Revisar contra ALB-04 (la prueba existente es de sincronización de álbumes) antes de dar el riesgo por cerrado.
