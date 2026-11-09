# Instrucciones del proyecto ClickTap

## Especificación de requisitos (obligatorio)

La especificación viva está en `docs/requisitos/` (índice y convenciones en `docs/requisitos/README.md`).

Al implementar, cambiar o eliminar una funcionalidad, **actualiza la especificación en el mismo commit que el código**:

1. Busca el módulo en `docs/requisitos/README.md`; si no existe, créalo con la plantilla de ese archivo y agrégalo al índice.
2. Añade o edita los requisitos afectados (ID permanente `PREFIJO-NN`, estado ✅/🟡/❌, pruebas que lo verifican). Si cambia un comportamiento, modifica el requisito; no dejes el anterior.
3. Mueve a `docs/requisitos/14-pendiente.md` lo que quede fuera y quítalo de allí cuando se construya.
4. Actualiza la fecha de «Última revisión» del módulo.

No declares un requisito ✅ sin una prueba automática que lo respalde; si solo se comprobó a mano, márcalo 🟡 con «Manual».

## Entorno

- Levantar Docker siempre con los dos archivos: `docker-compose.yml` + `docker-compose.supabase.yml`.
- Pruebas: `docker compose -f docker-compose.yml -f docker-compose.supabase.yml exec -T -e AUTH_DB_CONNECTION=sqlite app php artisan test --compact <ruta>`.
- No versionar `src/.env.supabase` ni `src/.env.local-respaldo` (contienen secretos).
