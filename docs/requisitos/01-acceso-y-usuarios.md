# 01 — Acceso y usuarios
Última revisión: 2026-11-09 · Estado: ✅ (sin roles)

## Objetivo
Controlar quién entra al panel de administración y mostrar el estado de las conexiones.

## Actores
Administrador (usuario con `activo = true`), socio (alta por consola).

## Requisitos
| ID | El sistema debe… | Estado | Verificación |
|---|---|---|---|
| ACC-01 | Redirigir a los visitantes sin sesión al inicio de sesión de `/admin`. | ✅ | AdminUiTest: guests_are_redirected_to_the_existing_login |
| ACC-02 | Permitir el acceso al panel solo a usuarios activos; un usuario inactivo no puede entrar ni leer el catálogo privado ni la vista previa. | ✅ | AdminPanelTest: active_administrator_can_open_the_content_screens; SharedCatalogTest: guest_and_inactive_account_cannot_read_private_catalog_or_preview |
| ACC-03 | Dar de alta a un socio con `clicktap:create-partner correo --name=`, pidiendo una contraseña oculta de al menos 12 caracteres, sin modificar cuentas existentes y exigiendo terminal interactiva. | 🟡 | Sin prueba automática (comando probado a mano). |
| ACC-04 | Mostrar en la barra superior el estado de conexión (Supabase, sincronización, worker de publicación). | ✅ | PublicacionesSitioTest: connection_button_shows_the_queue_and_worker_signal, connection_button_level_follows_worker_queue_and_failures |
| ACC-05 | Ofrecer cerrar sesión y ver el perfil desde el pie del menú lateral. | 🟡 | Manual. |
| ACC-06 | Usar el mismo panel Filament para el resumen y las pantallas de contenido, con navegación plana. | ✅ | AdminUiTest: dashboard_uses_the_same_filament_panel_as_content_screens, sidebar_displays_flat_navigation_without_removed_screens |
| ACC-07 | Registrar qué socio hizo cada cambio compartido. | ✅ | SharedCatalogTest: partners_share_clients_and_locales_and_changes_record_the_actor |

## Reglas de negocio
- No hay roles: todo usuario activo ve y modifica todo. Los roles propietario/editor y solo lectura son un requisito futuro (PEN-02).
- Las pruebas deben ejecutarse con `AUTH_DB_CONNECTION=sqlite` para no tocar Supabase.

## Limitaciones y pendientes
Roles y propiedad (PEN-02); prueba automática del alta de socios.
