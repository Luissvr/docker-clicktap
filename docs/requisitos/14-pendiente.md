# 14 — Pendiente: lo que no existe o está a medias
Última revisión: 2026-11-09

Cuando algo de esta lista se construya, **se mueve a su módulo** con requisitos y pruebas, y se borra de aquí.

| ID | Pendiente | Detalle | Módulo destino |
|---|---|---|---|
| PEN-01 | Portal «Mi web» y módulo Sitios web | Plan 3 (`PLAN_03_GESTION_WEB_Y_LANDINGS.md`): solo existe el documento. | nuevo |
| PEN-02 | Roles y propiedad | `User` solo tiene `activo`; falta propietario, editor y solo lectura. | 01 |
| PEN-03 | Pantalla de configuración de precios | `ConfiguracionPrecios` es un marcador «En preparación». Hoy: Stock → precio sugerido y cifras fijas en Ventas. | 07 / 08 |
| PEN-04 | Listar todos los negocios homónimos | Google Places API (New) Text Search (clave y facturación, decisión D2) o lista secundaria con Brave. | 12 |
| PEN-05 | Segunda fuente automática de redes | Serper.dev o SerpApi tras Brave. Mientras tanto: pegar el enlace (INV-33). | 12 |
| PEN-06 | Versionado y aprobación de páginas | Tablas `versiones_pagina` y `enlaces_version` sin uso. | 03 |
| PEN-07 | Placas NFC y solicitudes de cambio | Tablas `placas_nfc`, `asignaciones_placa`, `solicitudes_cambio` sin uso. | nuevo |
| PEN-08 | Cola de publicación durable | Hoy en archivos, sin idempotencia ni soporte multi-instancia. | 05 |
| PEN-09 | Refactor de `BusinessLedger` (~850 líneas) | Dividir en Inventario, Compras, Catálogo y Publicación. | 08 |
| PEN-10 | Conciliación contable formal y reportes | Cobro de mantención mensual (`cobros`) sin uso. | 09 |
| PEN-11 | Aislamiento de pruebas | Forzar `AUTH_DB_CONNECTION=sqlite` en `phpunit.xml`; hoy 136 pruebas fallan sin el parámetro y una mal configurada podría tocar Supabase. | 13 |
| PEN-12 | Integración continua | No hay flujos de trabajo; `quality:pages` se ejecuta a mano. | 13 |
| PEN-13 | Alojamiento siempre disponible y acceso móvil remoto | Cloudflare Tunnel + Access o nube. | 13 |
| PEN-14 | Vista previa pública Astro de borradores | Sin ruta pública hoy. | 05 |
| PEN-15 | Benchmarks de rendimiento | p50/p95 con 100, 1.000 y 10.000 páginas; separar el sondeo del resumen. | 13 |
| PEN-16 | Validación en móvil físico | Acciones táctiles, pagos y stock, zoom y teclado. | 03 / 08 |
| PEN-17 | Indicadores del resumen | El panel inicial solo tiene atajos: faltan ventas del mes, stock bajo y páginas por publicar. | nuevo |
| PEN-18 | Limpiar tablas heredadas sin uso | `publicaciones_sitio`, `detalles_publicacion`, `configuracion_precios`: eliminar con migración o retomar. | 10 |
| PEN-19 | Corregir el salto a la pestaña «Enlaces» al aplicar una investigación | | 12 |
| PEN-20 | Reducir la latencia de la lista de candidatos (~10–14 s) | | 12 |
| PEN-22 | Homónimo de otra ciudad en las sugerencias de Brave | «Casa Minga» (Concepción) recibió «Casa Galería Café \| Bogotá». Hoy el administrador lo ve en la vista previa (INV-37); falta además descartar automáticamente resultados cuya ubicación sea otra ciudad. | 12 (INV-30) |
