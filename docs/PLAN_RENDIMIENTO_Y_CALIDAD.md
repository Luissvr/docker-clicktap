# Plan independiente: rendimiento y bases de calidad

Fecha: 2026-11-05. Estado: proyección comercial local integrada; presupuestos p95 y pruebas de escala pendientes.

Objetivo: resolver la demora al abrir Actividad, filtrar y navegar, y establecer condiciones verificables para toda integración futura. Complementa `BASE_REGLAS_VISTAS_ADMIN.md` y `PLAN_MODULARIZACION_NUBE.md`; no sustituye sus garantías de datos. Las omisiones visuales documentadas en entregas antiguas no cuentan como aceptación de esta entrega.

## Q1. Diagnóstico reproducible

Instrumentar tiempo desde clic hasta feedback y contenido útil; separar navegación, consulta SQL local/remota, caché, serialización Livewire, transferencia y render. Registrar identificador de petición/operación sin secretos ni contenido privado.

Medir abrir Páginas, Actividad, cambio de cada estado, búsqueda, álbum, orden, cantidad, paginación, regreso desde edición y guardado. Registrar p50/p95, errores, cantidad/duración de consultas, solicitudes externas, tamaño de respuesta y tareas largas del navegador.

Usar mismo equipo, build de producción, viewport y dataset antes/después: 100, 1.000 y 10.000 páginas con ventas/álbumes representativos. Medir primera carga y sesión caliente separadamente, con 30 repeticiones por flujo tras calentamiento y una prueba adicional de cinco usuarios concurrentes. No generar datos en producción. Documentar región/red real y simular latencia remota de 200/800 ms, desconexión y worker detenido.

Hallazgos estáticos que justifican investigar, no equivalen a perfil medido:

- `getViewData()` recupera IDs comerciales remotos y ventas en cada render.
- Calcula historial aun cerrado; abrir Actividad vuelve a recorrer el listado.
- `pageStatus()` se llama por tarjeta y repite lecturas de cola/tracker.
- Polling de página completa cada diez segundos durante operaciones.
- `CatalogConnection` puede recurrir a remoto cuando la copia local no existe; el comportamiento degradado debe ser explícito.
- Sincronización completa usa espera de lock de hasta 30 segundos. Trazar si algún flujo interactivo llega allí; no afirmar que cause cada demora sin medirlo.

Entregable: baseline y trazas que atribuyan las demoras a causas concretas; hipótesis descartadas también registradas.

## Q2. Desacoplar interacción y trabajo remoto

1. Separar consultas de listado, resumen comercial y actividad. Abrir/cerrar modal con estado local inmediato; componente independiente para datos de actividad. El contenido cerrado no se calcula.
2. Implementar una proyección local mínima de estados comerciales para el listado, con versión, fecha de actualización e invalidación tras escrituras. No replicar contabilidad completa para resolver una etiqueta. Toda transición comercial revalida en la fuente autoritativa.
3. Filtrar/ordenar/paginar en servidor sobre la proyección; evitar descargar todos los IDs de ventas para construir `whereIn`. Revisar índices y planes de consulta con el dataset representativo.
4. Leer cola y tracker una vez por petición y mapear estados para todos los IDs visibles. Agregar contadores en consultas acotadas; evitar lecturas repetidas de esquema y consultas por tarjeta.
5. Invalidar caché por cambio de datos y contexto/usuario; claves incluyen filtros, álbum, orden y tamaño. Mostrar actualización pendiente cuando una escritura ya se confirmó remotamente pero la lectura local aún no convergió. Nunca confirmar visualmente un guardado fallido.
6. Sincronización, publicación y comprobación de Cloudflare se ejecutan en workers. Navegación y filtros no disparan sincronizaciones completas ni verificaciones externas. La indisponibilidad remota no bloquea consultar la copia local.
7. Polling solo de estado ligero mientras modal visible u operación propia activa; intervalo inicial propuesto de 3 segundos en modal y 10 segundos en resumen, con backoff hasta 30 ante errores. Suspender con pestaña oculta y evitar peticiones solapadas. No volver a renderizar todas las tarjetas por cada señal del worker.
8. Búsqueda con debounce de 300 ms y control de respuestas atrasadas; últimas opciones elegidas prevalecen. Preservar contexto de navegación sin listeners duplicados tras `wire:navigate`.
9. Reducir peso del listado: portadas con dimensiones reservadas, carga diferida y recurso adecuado; no montar iframes de preview por tarjeta. Recursos del modal se cargan bajo demanda.

## Q3. Requisitos de calidad exigibles

Metas iniciales de aceptación; no son resultados ya obtenidos. Si una meta es inviable, presentar evidencia y decidir el cambio de presupuesto explícitamente, sin relajarla de forma silenciosa.

| Dimensión | Requisito y evidencia |
| --- | --- |
| Respuesta inmediata | Feedback visible de controles ≤100 ms p95; apertura visual del modal ≤150 ms p95 |
| Consulta habitual | Filtros, orden y paginación con copia local preparada ≤500 ms p95 desde clic hasta contenido estable |
| Actividad | Primer contenido local útil ≤500 ms p95; carga remota pendiente nunca bloquea apertura/cierre |
| Primera carga | Páginas utilizable ≤1,5 s p95 en entorno acordado; medir carga fría por separado |
| Dependencias remotas | Cero consultas a Supabase/Cloudflare en navegación o filtrado con proyección local válida; evidencia en trazas |
| Escalabilidad de consultas | Número de consultas no crece linealmente al pasar de 10 a 100 tarjetas; fijar límite exacto después del baseline y automatizarlo |
| Fluidez | No introducir tareas largas >50 ms atribuibles a animación; ninguna animación ni polling del modal cerrado |
| Estabilidad visual | CLS objetivo ≤0,1; portadas, skeleton y contenido reservan espacio |
| Integridad | Acciones comerciales transaccionales e idempotentes; concurrencia/doble clic no duplican ventas ni solicitudes; revalidar permisos y estado en servidor |
| Veracidad | Guardado, réplica, envío y despliegue son etapas distintas; error, señal vencida y sin verificar se muestran explícitamente |
| Resiliencia | Desconexión, timeout, reinicio de worker y retraso de réplica conservan contexto; reintento seguro y sin falso éxito |
| Accesibilidad | Teclado, foco visible, modal con nombre/trap/retorno, contraste suficiente, texto junto al color y movimiento reducido |
| Adaptabilidad | Sin desbordamiento desde 320 px; cinco columnas en escritorio según plan funcional y controles accesibles con zoom |
| Seguridad | Autorización en lecturas/acciones, aislamiento de caché, errores saneados, URLs de servicios configuradas y sin credenciales expuestas |
| Mantenibilidad | Una definición de estados y transiciones, consultas separadas de efectos, componentes con responsabilidad concreta y dependencias justificadas |

## Q4. Puertas de calidad para cualquier integración

Antes de implementar, cada cambio debe especificar: problema y caso de uso, reglas de estado, fuente de verdad, permisos, comportamiento con datos vacíos/error/latencia, presupuesto de rendimiento, alcance visual y pruebas de aceptación. No basta una lista de componentes a crear.

Durante implementación: mantener cambios acotados, reutilizar stack real y componentes, separar consultas de mutaciones y dar identificador a trabajos asíncronos. Toda migración incluye compatibilidad de datos previos y estrategia de reversión. Publicación o reconciliación no ocurre como efecto lateral de renderizar.

Antes de integrar, pasar estas puertas:

1. **Funcional:** pruebas de negocio y permisos; transiciones válidas/invalidas, consultas combinadas y escritura seguida de lectura coherente.
2. **Datos y fallos:** PostgreSQL para concurrencia, sincronización y restricciones; servicios externos aislados en pruebas; simular fallos y verificar recuperación.
3. **Rendimiento:** comparar con baseline y presupuestos bajo las mismas condiciones. Bloquear si se supera presupuesto o existe regresión p95 >10% reproducible en tres corridas. No usar CI compartida ruidosa como único benchmark.
4. **Visual y accesibilidad:** Playwright y revisión de capturas a 390, 1024, 1440 y 1920 px, además de comprobación de 320 px/zoom/teclado/movimiento reducido. Verificar el build servido realmente.
5. **Operación:** build completo, migraciones ensayadas, workers observables, secretos protegidos y procedimiento de reversión probado para cambios de datos.

Para PHP modificado ejecutar Pint según `src/AGENTS.md`; correr primero las pruebas estrechas afectadas y luego las regresiones justificadas. Compilar con `npm run build:admin` para cambios del tema y `npm run build` cuando cambien recursos generales. Ejecutar las pruebas UI pertinentes mediante el script existente, sin publicar en servicios reales.

CI deberá ejecutar build, pruebas funcionales seleccionadas, pruebas de integración de datos y smoke de navegador. Añadir aserciones de ausencia de red remota en filtros, de historial no consultado cerrado y de consultas sin crecimiento por tarjeta. Medir tiempos de navegador en entorno controlado, guardando artefactos.

## Q5. Despliegue, observación y definición de terminado

Entregar por cambios revisables: diagnóstico → lecturas/actividad independientes → consistencia y álbumes → UI → regresión final. Activar primero en entorno de prueba con datos representativos y fallos simulados. Para producción, seguir el procedimiento autorizado de despliegue y conservar versión anterior compatible; no revertir datos nuevos mediante borrado indiscriminado.

Observar p95 de rutas, errores, antigüedad de proyección, heartbeat y edad de cola. Diferenciar tiempo de respuesta del panel de tiempo total de publicación. Alertas por trabajos estancados o datos vencidos deben basarse en umbrales documentados.

Una tarea se considera terminada solo con: requisitos cubiertos, tests relevantes aprobados, build vigente, comparación antes/después, evidencias visuales, recuperación ante fallos y limitaciones explícitas. Que una funcionalidad exista en el código o que una prueba unitaria pase no demuestra que navegue bien ni que esté integrada.

## Avance de implementación del 2026-11-05

- El listado reutiliza por 60 segundos una lectura compacta de ventas y la invalida tras las transiciones comerciales ejecutadas por la aplicación. Si Supabase falla después de una lectura válida, muestra el último estado conocido con aviso visible. Una sesión fría sin datos previos sigue necesitando Supabase.
- El historial se calcula solo al abrir Actividad. El modal muestra respuesta visual inmediata y su componente consulta y sondea la actividad sin volver a renderizar el listado completo. La vista principal deja de sondear mientras el modal está abierto.
- Los estados de publicación de las tarjetas se resuelven en lote; la disponibilidad remota de la migración de papelera se memoriza 60 segundos.
- `CLICKTAP_PERFORMANCE_LOGGING=true` habilita trazas del panel con identificador, interacción permitida, duración, número y tiempo de consultas, y consultas directas a Supabase. No registra SQL, parámetros ni contenido. `catalog_queries` identifica consultas en la conexión mixta, sin afirmar que todas fueron locales o remotas.
- Pruebas de regresión verifican reutilización de lectura comercial, invalidación tras rechazo, degradación ante caída remota, historial cerrado, actividad y estados de cola. El build de administración se comprobó.
- `npm run quality:pages` ejecuta el build de administración, las regresiones del área y la comprobación del diff como puerta local repetible.

Avance 2026-11-06: `paginas.estado_comercial` y `paginas.venta_id` son la proyección persistente del listado; la sincronización local replica ambas columnas e indexa estado, venta, fechas y nombres. Los filtros y contadores ya no descargan IDs de ventas. La venta vinculada se revalida en Supabase al iniciar o confirmar una transición. Las trazas incluyen tamaño de respuesta y distinguen orden y cantidad. Una prueba confirma cero lecturas de ventas al filtrar. Pint, build y 79 pruebas funcionales pasaron; siete pruebas de sincronización pasaron en PostgreSQL aislado, incluido cambio y eliminación de álbum con sus páginas preservadas. La sincronización real de 122 páginas se ejecutó sin error.

Pendiente para satisfacer todo el plan: medir p50/p95 reales antes/después en 100, 1.000 y 10.000 páginas, 30 repeticiones y cinco usuarios concurrentes; simular latencia/fallos remotos en entorno aislado; separar también el sondeo del resumen; automatizar benchmark y smoke visual en CI con credenciales de prueba. Ningún presupuesto de latencia se declara alcanzado todavía.
