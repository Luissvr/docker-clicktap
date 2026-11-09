# 13 — Operación y procesos en segundo plano
Última revisión: 2026-11-09 · Estado: ✅

## Objetivo
Dejar documentado cómo se levanta el sistema y qué procesos y comandos lo sostienen.

## Requisitos
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| OPE-01 | Levantarse con **dos archivos de Compose** (`docker-compose.yml` + `docker-compose.supabase.yml`); con solo el primero el contenedor `app` pierde Supabase y el login deja de funcionar. | ✅ | Manual (incidente del 2026-11-09, sin pérdida de datos). |
| OPE-02 | Mantener cuatro procesos en segundo plano: planificador (poda diaria 03:15), `publication-worker`, `sync-worker` y `research-worker`, junto a los servicios `app`, `db` y `web` (`127.0.0.1:8080`). | ✅ | Manual. |
| OPE-03 | Ofrecer los comandos `export-public`, `create-partner`, `research-route`, `create-route-pages`, `research-website`, `cache-supabase-logos`, `migrate-logos-to-supabase`, `sync-local-catalog`, `prune-page-trash` y `prepare-shared-database`. | ✅ | Parcial: ver pruebas de cada módulo. |
| OPE-04 | Respaldar y restaurar la base con `scripts/backup` y `scripts/restore` (`.ps1`/`.sh`) y preparar la publicación con `prepare-publish.ps1`. | 🟡 | Manual. |
| OPE-05 | Ejecutar las pruebas con `docker compose exec -T -e AUTH_DB_CONNECTION=sqlite app php artisan test --compact`. | 🟡 | Solución provisoria; ver PEN-11. |
| OPE-06 | No cargar recursos del panel en el frontend público. | ✅ | AdminUiTest: public_frontend_does_not_load_admin_assets |
| OPE-07 | Ejecutar integración continua automática. | ❌ | Ver PEN-12. |

## Reglas de negocio
- Los archivos `src/.env.supabase` y `src/.env.local-respaldo` contienen secretos y **no se versionan**.
- Las rutas web son `/`, `/preview/{slug}` (autenticada) y `/media/page-logo/{pagina}/{version}`.

## Limitaciones y pendientes
Todo corre en el PC del administrador; no hay acceso remoto ni CI (PEN-12, PEN-13).
