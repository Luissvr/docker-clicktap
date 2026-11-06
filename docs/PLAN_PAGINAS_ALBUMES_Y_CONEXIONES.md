# Plan de implementación: páginas, álbumes y conexiones

Fecha: 2026-11-05. Estado: implementación funcional integrada; aceptación de rendimiento y prueba de datos a escala pendientes.

Solicitud: corregir creación de páginas, completar álbumes, mostrar cinco tarjetas por fila, recuperar controles de listado, sustituir el panel lateral de actividad por un modal central y corregir estados/acciones. El rendimiento y las condiciones de entrega se desarrollan en `PLAN_RENDIMIENTO_Y_CALIDAD.md` y bloquean la aceptación de este plan.

## 1. Base verificada y alcance

La implementación actual de estas pantallas es Filament/Livewire/Blade. El tema del administrador se compila con Tailwind 3; no migrar esta tarea a React ni aplicar APIs de Tailwind 4 por asumir el stack de otras guías.

Hallazgos del código, sin atribuirles todavía tiempos medidos:

- `PublicacionesSitio::getViewData()` pagina a 12 y ordena únicamente por `updated_at`; Blade usa hasta tres columnas.
- El mismo método consulta ventas mediante `BusinessLedger::connection()`, que apunta a Supabase cuando está activo el catálogo local. Lee el historial aunque el modal esté cerrado y consulta estado de cola por cada tarjeta.
- Hay modelo, migración, controles y relaciones para álbumes. La vista depende de comprobar tablas locales, mientras crear comprueba tablas remotas. Falta verificar migraciones y convergencia en ambos destinos.
- `LocalCatalogSync::TABLES` incluye álbumes y relaciones, pero `syncRecord()` solo acepta clientes, locales y páginas. Crear un álbum necesita una vía de sincronización parcial o reconciliación explícita, sin ocultar el resultado mientras llega la réplica.
- El modal tiene altura completa y estilo de panel lateral; la página completa hace polling cuando hay publicaciones pendientes.
- El editor utiliza alturas calculadas y scroll interno, y mantiene bordes de secciones. La causa exacta de la franja inferior requiere inspección del DOM y estilos computados.

Preservar el trabajo local existente: hay modificaciones previas en los archivos implicados. Las capturas son referencias visuales, no instrucciones técnicas adicionales. No ejecutar migraciones ni publicar páginas como parte de esta fase de planificación.

## 2. Contrato de estados confirmado por el usuario

Cada página presenta dos campos claramente separados:

| Campo | Valores y comportamiento |
| --- | --- |
| Estado comercial | Por vender por defecto al crear; solo permite pasar a Vendida o Rechazada |
| Estado técnico | Resultado verificable de guardado, sincronización, publicación o retirada; incluye pendiente, procesando, confirmado, error y sin verificar según la operación |

La intención de una página Por vender es estar en producción. Crear inicia automáticamente el flujo técnico de publicación tras guardar correctamente. Mientras se completa, conservar Por vender y mostrar la operación pendiente en el campo técnico; nunca asegurar que Cloudflare ya la sirve sin evidencia.

No usar Publicadas como categoría comercial, contador ni filtro principal. Conservar el estado técnico interno de publicación que necesitan el publicador y el seguimiento de Cloudflare.

| Estado comercial actual | Transición disponible | Efecto |
| --- | --- | --- |
| Por vender | Marcar vendida | Confirmar mediante el flujo comercial existente y los datos obligatorios; no inventar pagos ni movimientos contables |
| Por vender | Rechazar | Solicitar motivo, persistir rechazo y solicitar retirada técnica |
| Vendida | Ninguna transición comercial en esta tarjeta | Consultar/editar contenido según permisos; correcciones comerciales pertenecen al flujo específico de ventas |
| Rechazada | Ninguna transición comercial en esta tarjeta | Mostrar retirada pendiente/confirmada/fallida y permitir reintento técnico cuando proceda |

Implementar estas restricciones en servidor, además de ocultar botones. Una página rechazada sin venta asociada también debe conservar su rechazo: hoy desactivar la página no proporciona por sí solo un estado comercial persistente.

Resolver una única fuente de verdad comercial: reutilizar ventas para páginas con venta y establecer persistencia explícita para páginas sin venta; centralizar la resolución y transiciones para evitar dos valores contradictorios. Antes de migrar, inventariar registros: confirmadas → Vendida, rechazadas → Rechazada, restantes válidas → Por vender. Separar cancelaciones pendientes y casos ambiguos para conciliación; no convertirlos silenciosamente. Preservar papelera y auditoría, y no iniciar publicaciones masivas por reclasificar datos históricos.

Eliminar Historial de las tarjetas; conservar auditoría interna y Actividad global. En Por vender eliminar Publicar y Retirar del sitio como acciones comerciales redundantes: publicar nuevas revisiones al guardar, y retirar mediante Rechazar. Ofrecer reintento técnico únicamente ante una operación fallida. Mantener Editar, Vista previa y Abrir sitio cuando exista URL válida. Papelera queda como administración secundaria, con protecciones existentes y sin sustituir el rechazo.

Aceptación: creación siempre Por vender; ninguna tarjeta ofrece transiciones incompatibles; Vendida aparece en filtros y contadores; un rechazo sin venta persiste tras recargar; un doble clic no duplica la operación comercial.

## 3. Editor sin franja ni contornos de contenedores

1. Inspeccionar altura disponible, padding inferior, acciones de formulario y wrappers de Filament en crear y editar. Identificar qué elemento produce la franja de la captura.
2. Sustituir offsets frágiles por un layout que distribuya la altura disponible con `min-height: 0` y una política de scroll por panel; permitir flujo normal en móvil.
3. Quitar bordes, rings decorativos y sombras que dibujen contornos alrededor del formulario y la vista previa. Acotar estilos al editor para no afectar otros módulos.
4. Mantener legibilidad de inputs, estados de validación y foco visible de teclado; los contornos decorativos y los indicadores funcionales tienen propósitos distintos.
5. Garantizar que Guardar/Crear siga accesible y que la vista previa no recorte contenido ni deje un bloque vacío inferior.

Archivos: `PaginaResource.php`, páginas Create/Edit, componentes `editor-form-actions`, `editor-navigation`, `pagina-live-preview` y `theme.css`.

Aceptación visual: sin franja sobrante ni marcos de contenedores a 390, 1024, 1440 y 1920 px; sin scroll horizontal desde 320 px; campos largos, errores y zoom de 200% utilizables.

## 4. Álbumes completos

1. Verificar y aplicar en la fase de implementación la migración de `albumes_paginas` y `album_pagina` en las conexiones previstas, con claves foráneas, índice inverso por página y reversión que no borre páginas.
2. Corregir sincronización parcial de álbumes y de su relación con páginas, incluyendo altas, asignaciones, desasignaciones y borrados. Respetar orden de padres/hijos al copiar relaciones y la reconciliación completa existente.
3. Mostrar siempre la sección Álbumes: disponible, vacía, cargando, pendiente de sincronización o con error explicativo. No desaparecer silenciosamente por un `hasTable` falso.
4. Permitir crear, renombrar, editar fecha/sector, asignar varias páginas, quitar una página y eliminar álbum conservando sus páginas.
5. La selección para álbumes admite páginas Por vender, Vendidas o Rechazadas; no reutilizar la restricción de borradores de las acciones masivas destructivas. Excluir papelera salvo restauración.
6. Tras confirmar una escritura, reflejarla en la lectura siguiente sin pedir recargar ni duplicar la operación si la copia local está atrasada. Mostrar antigüedad y sincronización pendiente.

Aceptación: crear álbum → asignar páginas → filtrar → recargar conserva el resultado; eliminar álbum conserva páginas; funcionan catálogo local, conexión directa y retraso temporal de réplica; permisos comprobados también en servidor.

## 5. Listado de cinco tarjetas y controles recuperados

- Cinco columnas en escritorio amplio desde 1440 px con sidebar abierto o cerrado; cuatro a 1280, tres a 1024, dos a 640 y una por debajo. Validar el ancho útil real para evitar botones cortados.
- Cada tarjeta muestra portada, identidad, estado comercial, estado técnico y acciones compactas. El detalle de conexiones pertenece al modal.
- Ordenar por: actualización reciente/antigua, creación reciente/antigua y nombre A–Z/Z–A. Usar lista permitida de columnas/direcciones y desempate estable por ID.
- Mostrar: 10, 25, 50 o 100 páginas; valor inicial 25. Paginar en servidor, sin descargar el catálogo completo.
- Combinar búsqueda, álbum, estado, orden y cantidad; preservar parámetros en URL y navegación atrás. Reiniciar a página 1 al cambiar filtros o tamaño y limpiar selección que ya no corresponda.
- Filtros comerciales: Todas, Por vender, Vendidas, Rechazadas; Papelera separada como condición administrativa. Los contadores comerciales respetan búsqueda y álbum con semántica consistente.

Aceptación: cinco tarjetas completas por fila a 1440 y 1920; 25 resultados forman cinco filas completas cuando hay suficientes registros; todos los filtros se combinan y mantienen orden estable; vacío por álbum también tiene mensaje correcto.

## 6. Modal central de Actividad y conexiones

Abrir desde el centro con overlay, ancho máximo aproximado de 960 px y alto máximo de 85dvh, contenido con scroll interno. En móvil ocupar el ancho disponible con margen. Escape, cierre por botón, focus trap y retorno al disparador. Abrir la estructura inmediatamente y cargar datos en un componente independiente.

Diseño de conexiones:

```text
                       Supabase [Abrir servicio]
                         ⇅ guardado / réplica
                    Servidor ClickTap
                         ↓ publicación
                 Repositorio / compilación
                         ↓ despliegue
                      Cloudflare [Abrir servicio]
```

El servidor es el nodo central con icono de servidor; Supabase y Cloudflare tienen logos oficiales locales. La ruta hacia Cloudflare reconoce el repositorio intermedio que usa el publicador; no representar una conexión directa Supabase → Cloudflare inexistente.

Cada conexión muestra operación, estado textual, última actualización, duración disponible y error resumido. Al seleccionar una conexión se ve su detalle y operación/revisión correlacionada. Enlaces configurados al proyecto concreto de Supabase y Cloudflare, nueva pestaña, sin tokens ni secretos. Si no están configurados, mostrarlo y no inventar una URL de proyecto.

Contrato de datos: identificador de operación y página/revisión, servicio, dirección, etapa, estado, inicio, última señal, fin, error saneado y URL autorizada. Reutilizar `CatalogSyncState`, `PublicationQueue`, `PublicationHistory` y `CloudflareDeploymentTracker`; añadir solo la información que falte.

Animación basada en eventos: líneas quietas sin trabajo; partícula direccional durante transferencia real; indicador de espera al compilar; marca fija tras confirmación; error detenido con texto y reintento permitido. Un heartbeat no equivale a una transferencia. Una señal antigua significa Sin señal reciente, no necesariamente caída confirmada. No simular porcentajes ni marcar un commit enviado como desplegado.

### Hyperframes y especificación visual

Skill instalada con el comando solicitado (se agregó `--yes` para instalación no interactiva): `npx skills add https://github.com/heygen-com/hyperframes --skill hyperframes --yes`.

Se revisaron `.agents/skills/hyperframes/SKILL.md` y `references/motion.md`. Hyperframes trabaja con composiciones HTML y timelines deterministas para video; se utilizará en implementación para prototipar y revisar el movimiento con datos de ejemplo claramente identificados. La interfaz final debe seguir siendo interactiva y reflejar datos reales.

Preparar composición breve editable con escenas de reposo, sincronización, despliegue y fallo. Buscar primitivas en su catálogo antes de escribir efectos; validar con `hyperframes check --json` y snapshots. Traducir la coreografía validada al componente Blade/Alpine/SVG, sin cargar un motor de render de video en el listado.

Entrada del modal: opacidad y escala 0.98→1 en 180 ms; salida en 120 ms. Flujo de conexiones: velocidad constante y animación solo mientras la operación esté activa. Textos y nodos permanecen estables. Sin rebotes, respiración ornamental ni blur costoso. Con movimiento reducido, marcas estáticas y cambios de texto. Al cerrar, pausar animaciones y consultas. Estas duraciones se adaptan a interacción de software, no a una película.

Aceptación: modal centrado, conexiones legibles y clicables, éxito/error/retraso verificables, sin actividad artificial, teclado completo y sin polling del catálogo entero.

## 7. Secuencia de ejecución y cierre

1. Registrar baseline y proteger contra regresiones de rendimiento (plan separado, fases Q1–Q2).
2. Implementar contrato comercial/técnico y migración conservadora de datos; verificar integridad antes de cambiar botones.
3. Completar sincronización de álbumes y consultas del listado.
4. Corregir editor, cinco columnas, filtros y tarjetas.
5. Prototipar animación con Hyperframes e integrar modal con estado real y carga independiente.
6. Ejecutar pruebas funcionales, mediciones y revisión visual en build de producción. No marcar finalizado sin evidencias de ambos planes.

Pruebas a ampliar: `PublicacionesSitioTest`, `LocalCatalogSyncTest`, `CatalogConnectionTest`, `PublicPagePublisherTest`, `CloudflareDeploymentTrackerTest`, `PageTrashTest` y `tests/Browser/admin-shell.spec.js`, además de pruebas específicas de álbumes/estado comercial. Cubrir transición inválida por llamada directa, rechazo sin venta, venta confirmada, doble clic, réplica atrasada y error de despliegue.

Entrega: cambios por fase revisables, capturas de escritorio/móvil, tabla de mediciones antes/después y comprobación de datos preservados. No modificar históricos ni publicar sitios reales como efecto colateral de pruebas.

## Avance verificado el 2026-11-06

- Se aplicaron migraciones de álbumes y estado comercial en Supabase. La copia local se reconstruyó con 122 páginas y nuevas columnas de estado y venta vinculada; no se creó ningún álbum ni página de prueba en el catálogo real.
- Crear deja la página Por vender, activa y con solicitud técnica de publicación. Editar solicita una nueva publicación tras confirmar el guardado. Marcar vendida abre una venta vinculada sin duplicarla; confirmar la venta actualiza Vendida de forma transaccional. Rechazar sin venta conserva motivo y auditoría. Las tarjetas ya no ofrecen Publicar, Retirar ni Historial.
- El listado usa el estado y la referencia de venta de la copia local; filtros, orden y paginación no construyen listas remotas de IDs. Álbumes, cinco columnas, controles, editor sin contornos y modal central se comprobaron en la aplicación servida. Anchuras 320, 390, 1024, 1280, 1440 y 1920 px no presentaron desbordamiento horizontal.
- Pint y build de administración pasaron. La regresión funcional terminó con 79 pruebas aprobadas y 560 aserciones; siete pruebas de sincronización y álbumes pasaron además contra PostgreSQL aislado. La sincronización real de Supabase a PostgreSQL local se ejecutó sin error.
- Los enlaces al panel de Supabase y Cloudflare requieren `SUPABASE_DASHBOARD_URL` y `CLOUDFLARE_DASHBOARD_URL`; cuando faltan, el modal lo muestra. El símbolo de Supabase procede de su [archivo oficial](https://github.com/supabase/supabase/blob/master/packages/common/assets/images/supabase-logo-icon.svg).

No se declara aceptación total: faltan mediciones p50/p95 en un entorno de prueba con 100, 1.000 y 10.000 páginas, las 30 repeticiones y concurrencia indicadas en el plan de rendimiento, simulación de retraso de réplica y la revisión de navegación con zoom/teclado en todos los anchos. Hyperframes se revisó como guía de movimiento; su prototipo de vídeo no se completó porque el comando CLI quedó bloqueado. La animación interactiva final funciona sin ese motor.
