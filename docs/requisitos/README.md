# Especificación de requisitos de ClickTap

Documento vivo. Cada funcionalidad tiene su propio archivo con requisitos numerados, reglas de negocio y las pruebas que los verifican. **Se actualiza en el mismo cambio que implementa la funcionalidad** (ver «Cómo mantenerla»).

Última revisión general: 2026-11-09 (rama `brandon`). Origen: análisis del código, rutas, acciones de Filament y pruebas automáticas; complementa los casos de uso de la nota 09 del Second Brain.

## Índice

| Archivo | Prefijo | Módulo | Estado general |
|---|---|---|---|
| [01-acceso-y-usuarios.md](01-acceso-y-usuarios.md) | ACC | Inicio de sesión, socios, estado de conexión | ✅ (sin roles) |
| [02-clientes-y-locales.md](02-clientes-y-locales.md) | CLI | Clientes y locales | ✅ |
| [03-paginas.md](03-paginas.md) | PAG | Editor de páginas, estado comercial y papelera | ✅ |
| [04-logos-y-medios.md](04-logos-y-medios.md) | LOG | Logos, paleta y caché local | ✅ |
| [05-publicacion.md](05-publicacion.md) | PUB | Publicación, retiro y seguimiento (GitHub + Cloudflare) | ✅ / 🟡 sin verificar en real |
| [06-albumes.md](06-albumes.md) | ALB | Álbumes de páginas | ✅ / 🟡 sincronización |
| [07-ventas.md](07-ventas.md) | VEN | Ventas de tarjeta Google | ✅ |
| [08-stock.md](08-stock.md) | STK | Stock, compras, pedidos y acrílicos | ✅ |
| [09-contabilidad.md](09-contabilidad.md) | CON | Caja, cobros y finanzas | ✅ |
| [10-catalogo-y-sincronizacion.md](10-catalogo-y-sincronizacion.md) | CAT | Doble origen de datos y sincronización | ✅ |
| [11-prospeccion-de-rutas.md](11-prospeccion-de-rutas.md) | RUT | Prospección de rutas desde Google Maps | ✅ |
| [12-investigacion-de-negocios.md](12-investigacion-de-negocios.md) | INV | Investigación automática de un negocio por nombre | ✅ / 🟡 |
| [13-operacion.md](13-operacion.md) | OPE | Comandos, respaldos, procesos en segundo plano | ✅ |
| [14-pendiente.md](14-pendiente.md) | PEN | Lo que no existe o está a medias | ❌ |

## Convenciones

- **ID:** `PREFIJO-NN`, permanente. Un requisito eliminado no reutiliza su número: se marca `Retirado`.
- **Estado:** ✅ implementado y verificado por pruebas · 🟡 implementado pero sin prueba automática o con limitación conocida · ❌ no implementado · ⛔ retirado.
- **Redacción:** «El sistema debe…» describe un comportamiento observable; las reglas de negocio van aparte y explican el porqué.
- **Verificación:** nombre del archivo de prueba y, si aplica, del método (sin el prefijo `test_`). «Manual» indica que solo se comprobó a mano; «—» que no hay verificación.
- **Alcance de la evidencia:** el estado refleja lo que el código y las pruebas demuestran. Nada se declara probado «en producción» sin evidencia: la publicación real a Cloudflare y las credenciales de GitHub no están verificadas en este repositorio.

## Cómo mantenerla

Al implementar, cambiar o eliminar una funcionalidad:

1. Busca el módulo en el índice. Si no existe, crea `NN-nombre.md` con la plantilla de abajo y agrégalo a la tabla.
2. Añade o edita los requisitos afectados **en el mismo commit** que el código. Si cambia un comportamiento, modifica el requisito; no dejes el anterior.
3. Actualiza el estado y la columna «Verificación» con las pruebas nuevas.
4. Mueve a [14-pendiente.md](14-pendiente.md) lo que quede fuera, y quítalo de allí cuando se construya.
5. Anota la fecha en «Última revisión» del módulo y, si cambia el estado general, en la tabla de arriba.

Plantilla de módulo:

```markdown
# NN — Nombre del módulo
Última revisión: AAAA-MM-DD · Estado: ✅

## Objetivo
## Actores
## Requisitos
| ID | El sistema debe… | Estado | Verificación |
## Reglas de negocio
## Limitaciones y pendientes
```

## Actores y sistemas comunes

- **Administrador:** usuario con `activo = true`. Hoy no hay roles: cualquier usuario activo entra al panel.
- **Socio:** administrador dado de alta con `clicktap:create-partner`.
- **Sistemas externos:** Supabase (escritura), copia local PostgreSQL (lectura), GitHub (repositorio Astro), Cloudflare Pages, Google Maps, Brave Search y OpenRouter (investigación de negocios).
