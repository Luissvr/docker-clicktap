# Plan 1 — Panel operativo en escritorio y móvil

Fecha: 28 de octubre de 2026. Revisión: ventas, stock, retirada de acceso y Publicaciones. Estado: propuesto para implementación; no acredita correcciones ni pruebas de navegador realizadas.

## Resultado esperado

Completar desde el teléfono el recorrido crear venta → preparar página → confirmar venta y stock → publicar → entregar, y el recorrido alternativo cliente no continúa → desactivar página → retirar su acceso público. Publicaciones será el centro para publicar, seguir operaciones, retirar acceso y reactivar expresamente; Ventas, Stock, Clientes y Contabilidad conservan sus operaciones y se conectan con ese centro. La prioridad inicial es resolver «Abrir acciones» sin respuesta en un móvil real y evitar operaciones cuyo resultado queda ambiguo.

Este plan y [el plan de publicación en Cloudflare](PLAN_02_PUBLICACION_CLOUDFLARE.md) son complementarios. Se conserva Laravel/Filament/Livewire. La guía React de ClickTap aporta identidad y criterios de interacción; introducir React o migrar de framework no es un requisito para reparar el panel actual.

## Evidencia y límites de la revisión

Revisión del código de trabajo, incluidos cambios locales preexistentes. La actualización de `origin/DEV` hasta `94ed2c4`, incorporada en `luis`, trae publicación individual. La integración conserva solicitudes por página en la cola y ajusta los textos de confirmación; no asume que cada clic publica todo el catálogo. El lock declara Laravel 13.35.0, Filament 3.3.56 y Livewire 3.8.10. El tema administrativo se compila con Tailwind 3, aunque también existe Tailwind 4 en el proyecto.

| Hallazgo | Evidencia local | Implicación |
| --- | --- | --- |
| «Abrir acciones» no responde en móvil | Reportado por el usuario; Páginas usa `ActionGroup` en `src/app/Filament/Resources/PaginaResource.php` | P0: reproducir y localizar la causa; todavía no se atribuye a CSS, Alpine o Livewire |
| La navegación del panel es SPA | `src/app/Providers/Filament/AdminPanelProvider.php`, `spa()` | Comprobar acciones tanto al entrar directamente como después de navegar sin recargar |
| La prueba de navegador solo cubre navegación y sidebar | `src/tests/Browser/admin-shell.spec.js` | Cambiar el viewport a 390 px no prueba toque, menús, formularios ni Safari |
| Las pruebas responsive verifican contenido HTML y reglas del servidor | `src/tests/Feature/ResponsiveContentTablesTest.php` | Que «Editar» exista en HTML no demuestra que el usuario pueda tocarlo |
| Publicar se muestra para páginas activas; el publicador exige además pertenecer a una selección | `PaginaResource.php`, `src/config/clicktap.php`, `src/app/Support/PublicPagePublisher.php` | Unificar elegibilidad antes de encolar y explicar qué falta |
| Resumen ofrece tres enlaces de navegación | `src/resources/views/filament/pages/dashboard.blade.php` | Convertirlo en un inicio orientado a tareas pendientes reales |
| Ya existen recuperación local de edición y navegación móvil del editor | `src/resources/views/filament/components/editor-navigation.blade.php` | Reutilizarlas; validar expiración, cambio de revisión y errores de almacenamiento |
| Publicaciones muestra un estado global y el último resultado | `src/app/Filament/Pages/PublicacionesSitio.php` y su vista Blade | Añadir listado por página, detalle, acciones e historial recuperable |
| Ventas ya permite crear borrador, crear/editar página y confirmar descontando stock | `src/app/Filament/Pages/Ventas.php` y `ventas.blade.php` | Completar el flujo existente y sus errores; no reconstruirlo como una función ausente |
| Eliminar borrador avisa que conservará la página | `src/resources/views/filament/pages/ventas.blade.php` | Añadir «Cliente no continúa» como operación distinta, con desactivación y retirada explícitas |
| Stock ya incluye compras, productos, reservas, ajustes, producción e historial | `src/resources/views/filament/pages/stock-tarjetas.blade.php` | Verificar cada operación real y su continuidad con ventas/publicaciones |

En la revisión inicial no se accedió a una sesión autenticada ni se reprodujo el fallo en un dispositivo. El usuario confirma que el bug no aparece en F12 y sí en un móvil real. La corrección no se dará por validada con emulación. Este documento distingue el alcance pendiente de las funciones existentes; los resultados de pruebas de implementación se registrarán por separado.

## Experiencia y flujos prioritarios

| Flujo | Escritorio | Móvil | Resultado verificable |
| --- | --- | --- | --- |
| Encontrar una página y actuar | Búsqueda, filtros y acciones legibles por registro | Tarjetas con nombre, estado, Editar y Más acciones; menú con opciones táctiles | La acción opera sobre el registro correcto y volver conserva búsqueda/filtros |
| Alta durante una visita | Datos mínimos, relación opcional con cliente y editor por secciones | Pasos cortos: Datos → Enlaces → Apariencia → Revisar; permitir volver sin pérdida | Se crea página y local una sola vez; el cliente sigue siendo opcional |
| Actualizar contenido | Editor y vista previa lateral cuando haya espacio | Una sección por vez y acceso visible a Guardar; vista previa a pantalla completa | Guardado confirmado; errores junto al campo y foco en el primero |
| Publicar y entregar | Publicaciones reúne la página seleccionada, cambios, alcance y seguimiento | Confirmación corta, seguimiento recuperable y Compartir enlace cuando corresponda | Guardado, encolado y disponible públicamente se distinguen |
| Registrar venta y entrega | Acceso a cliente/local/página desde el detalle | Formulario breve y resumen de importe, unidades y saldo antes de confirmar | Un reintento no duplica venta, pago ni movimiento de stock |
| Comprar y reservar tarjetas | Inventario con existencias, reservas y disponibilidad separadas | Compra/reserva con cantidades, coste y confirmación legibles | La disponibilidad cambia una sola vez y las cantidades insuficientes se explican antes de repetir |
| Cliente rechaza la venta | Revisar venta, página y consecuencias; seguir retirada en Publicaciones | Acción «Cliente no continúa», resumen y confirmación accesibles | Página desactivada y acceso público retirado con evidencia; historial conservado |
| Recuperar una oportunidad | Desde Publicaciones, revisar venta y reactivar expresamente | Una acción contextual y seguimiento de nueva publicación | Guardar o publicar otra página no reactiva una retirada |
| Retomar trabajo interrumpido | Recuperación local y advertencia de conflicto | Aviso de conexión y estado pendiente de confirmar; retomar al abrir el panel | Nunca se informa éxito por el solo hecho de tocar un botón |

Compartir usará la capacidad nativa cuando exista, con alternativa Copiar enlace y enlace seleccionable si el portapapeles falla. Descargar un QR puede incorporarse como entrega posterior; conservará el slug estable utilizado por placas y NFC. Abrir WhatsApp, teléfono, correo y mapas debe funcionar mediante enlaces compatibles, sin depender de hover o popups asíncronos.

## Entregas de implementación

### A. P0 — Diagnosticar y reparar las acciones táctiles

1. Preparar datos de prueba aislados: página activa incluida, activa excluida de publicación, inactiva, publicada y con nombre largo. Registrar modelo de teléfono, sistema, navegador, ruta y entrada directa o SPA del caso fallido.
2. Reproducir primero en el teléfono físico afectado: tocar el botón, comprobar apertura y elegir Editar; repetir Vista previa, Vista móvil y confirmación de Publicar en staging. Conservar evidencia antes/después. No exigir una prueba emulada fallida cuando el fallo no aparece en F12.
3. Inspeccionar mediante depuración remota cuando esté disponible: consola, carga de recursos, `aria-expanded`, eventos de apertura/cierre y el elemento que recibe el toque. Revisar overlays del sidebar, propagación de clic en tarjetas, recorte por `overflow`, posición fuera del viewport y reinicialización después de `wire:navigate`. Automatizar después la causa si resulta reproducible fuera del teléfono.
4. Corregir el componente o estilo responsable. Mantener los componentes soportados por Filament; evitar reglas globales de `z-index` o duplicar Alpine. Si hace falta un diálogo móvil para acciones, reutilizar las mismas definiciones y autorizaciones del servidor.
5. Hacer Editar directamente accesible en cada tarjeta. «Más acciones» agrupa las secundarias con nombre visible y área mínima de 44 × 44 px. Cierre al elegir, tocar fuera o pulsar Escape; devolver el foco al disparador.

**Cierre:** en el móvil físico afectado el menú abre al primer toque, permanece dentro del viewport y cada opción produce el resultado esperado al entrar directamente, navegar desde Resumen, filtrar, paginar y regresar desde edición. Sin errores de JavaScript ni acciones duplicadas. Sin acceso a ese dispositivo, declarar pendiente la validación física y no anunciar el bug como resuelto.

### B. P1 — Unificar estados y disponibilidad de acciones

1. Centralizar las condiciones de guardar/publicar/compartir/retirar/reactivar para que lista, editor, Publicaciones, modal y backend coincidan. La autorización del servidor sigue siendo obligatoria.
2. Mostrar la razón si una página está fuera de la selección de publicación; el usuario debe verla antes de entrar en cola. Validar de nuevo en el trabajador porque la configuración puede cambiar.
3. Separar «Cambios sin guardar», «Guardado», «En cola», «Enviada» y «Publicada verificada». Mostrar por separado acceso vigente y operación en curso: «Público · actualización fallida» o «Desactivada · retirada pendiente». Integrar los estados del plan 2 sin deducir éxito de un commit o de `activo=false`.
4. Cada operación muestra carga, éxito o error recuperable. Durante una escritura, bloquear su doble envío sin bloquear todo el panel. Con respuesta incierta, consultar el resultado antes de repetir.
5. Conservar contexto al volver: búsqueda, filtros, página del listado y posición cuando sea viable. Al salir con cambios, ofrecer Guardar, Continuar editando o Descartar.

**Cierre:** una página no elegible nunca se presenta como publicable; ningún estado de guardado implica publicación. Las respuestas 403, 419, validación y desconexión conservan un camino claro para continuar.

### C. P1 — Editor y entrega desde la calle

1. Extender el editor móvil existente con progreso por secciones y resumen de campos pendientes. En escritorio conservar edición amplia y preview; en móvil usar preview dedicada sin comprimir dos columnas.
2. Mantener Guardar alcanzable sin tapar campos, validaciones o teclado virtual; considerar área segura, orientación y altura dinámica. Probar el final de formularios y modales largos.
3. Carga de logo con progreso y errores de formato/tamaño. Evaluar reducción de imágenes antes de subir, conservando proporción y calidad; la validación del servidor es definitiva.
4. Ordenar enlaces mediante botones accesibles además de arrastrar. Comprobar enlaces desactivados, vacíos y de transferencia/copiar texto, con feedback real de copia.
5. Mantener la recuperación local de 24 horas: avisar que no incluye archivos, no sobrescribir una revisión nueva y eliminar la copia al guardar o cerrar sesión. No prometer publicación ni transacciones sin conexión.
6. Incorporar Compartir/Copiar enlace con el estado de la versión pública. Si hay cambios pendientes, explicar que se comparte la última versión publicada.

**Cierre:** completar alta, logo, tres enlaces, reordenación, guardado, preview y solicitud de publicación a 390 px usando solo controles visibles. Repetir con error de red y recuperación de formulario.

### D. P1 — Ventas y stock de tarjetas completos

1. Completar el recorrido existente Crear venta → guardar borrador → crear/editar página → volver a la misma venta → confirmar. Mostrar unidades, total recibido, disponibilidad actual y consecuencias antes de confirmar; mantener información introducida ante validación o stock insuficiente.
2. Hacer alcanzables Crear venta, Guardar, Confirmar y Cliente no continúa en móvil, sin depender de hover o del menú de acciones. Añadir búsqueda y estados explícitos para localizar oportunidades y ventas; una venta rechazada o cancelada nunca aparece como Borrador por una etiqueta binaria.
3. Conservar validación transaccional de disponibilidad e idempotencia de venta/pago/movimiento. Probar dos ventas simultáneas sobre la última tarjeta, doble toque, timeout después de confirmar y consulta posterior del resultado.
4. En Stock, verificar Añadir producto, Añadir compra, registrar pago de compra, reservar, entregar, cancelar reserva, ajustar y consultar historial. Mostrar existencia física, reservado y disponible por separado. Reservar reduce disponibilidad; entregar o cancelar produce únicamente el movimiento que corresponda, sin doble descuento o doble liberación.
5. Separar rechazo de oportunidad, cancelación de una venta confirmada y devolución física. Un rechazo conserva el registro y desencadena el retiro de la página mediante Publicaciones. No reintegrar tarjetas entregadas hasta registrar recepción física y condición utilizable; no devolver dinero automáticamente. Liberar reservas pendientes de esa operación cuando corresponda y registrar pagos/reembolsos con sus comprobaciones propias.
6. Si una página sirve a otras ventas vigentes, identificar el alcance antes de retirar: no inferir que rechazar una venta autoriza a afectar otras. Mostrar el vínculo en el resumen de confirmación y aplicar una regla explícita de negocio.
7. Adaptar compras, reservas, movimientos, pagos e historial a tarjetas etiquetadas en móvil. Importes en CLP, fechas en America/Santiago y estados expresados con palabras. Enlazar cada operación con la venta/página relacionada cuando exista, manteniendo el contexto al regresar.

**Cierre:** ejecutar en datos aislados alta y confirmación de venta, falta de stock, compra, reserva, cancelación de reserva, entrega, rechazo y devolución física. Verificar saldo, stock e historial tras navegar, reintentar y recargar. Las operaciones contables normales no publican el sitio; el rechazo con retirada sí genera la operación pública identificada en el plan 2.

### E. P1 — Publicaciones como centro de gestión

1. Reemplazar el resumen global como única pantalla por un listado de páginas con búsqueda por nombre/local/cliente y filtros Todas, Pendientes, Publicadas, Retiradas y Con error. Contadores reales y estado vacío con próxima acción clara.
2. En escritorio, presentar registros comparables; en móvil, tarjetas con nombre completo, venta relacionada, acceso vigente, operación en curso y siguiente acción. Editar página y Ver venta enlazan a sus módulos; el inventario relacionado se consulta en Stock sin duplicar su formulario aquí.
3. Ofrecer acciones contextuales directas: Revisar y publicar, Ver seguimiento, Desactivar y retirar acceso, Reactivar. Páginas y Ventas enlazan a este mismo flujo y comparten las reglas de servidor; no mantienen versiones contradictorias de la publicación.
4. Añadir detalle por página/operación con URL interna recuperable, resumen del contenido afectado, cronología, última comprobación pública y error accionable. Mantener los detalles técnicos de commit y build plegados. No inventar porcentajes; mostrar etapa y tiempo transcurrido.
5. Flujo Cliente no continúa: identificar venta y página → explicar efectos → confirmar → marcar página desactivada → iniciar retirada → verificar acceso eliminado. Conservar el historial administrativo. La revocación de rutas, aliases y recursos públicos se define en el plan 2; conservar el contenido interno no equivale a conservar acceso público.
6. Mientras se procesa mostrar «Desactivada · retirada pública pendiente». Si falla, advertir «La página puede seguir visible» y ofrecer reintentar la misma operación. Después de verificar mostrar «Acceso retirado». Nunca ocultar el fallo con un badge de éxito.
7. Reactivar exige acción explícita, revisión de situación comercial y nueva publicación verificada. Un guardado del editor, una publicación de otra página o un resultado tardío no puede reactivar una página retirada.
8. Diseñar y revisar estados de carga, sin resultados, sin conexión, sesión expirada, cola detenida, fallo de publicación, fallo de retirada y operación sustituida. Mantener filtros y posición al regresar de venta/editor.

**Cierre visual y funcional:** desde 320 px se reconoce la página, el acceso actual y la próxima acción sin abrir otro menú; no hay textos cortados, controles superpuestos ni scroll horizontal. Modales con nombre del local, consecuencias y confirmación visible incluso con teclado virtual. Foco correcto, áreas táctiles de 44 px, contraste y mensajes consistentes en lista/detalle. Completar publicar → seguimiento → rechazar venta → retirar acceso → reactivar en staging y conservar evidencia de cada estado.

### F. P2 — Inicio útil y continuidad

Resumen con accesos Crear venta, Crear página y Retomar edición, más pendientes reales de publicaciones/retiradas, stock bajo y sincronización atrasada. Cada indicador abre el listado filtrado correspondiente y muestra cuándo se actualizó. Relacionar cliente → locales → páginas y venta → publicación/entrega con regreso claro, sin exigir datos opcionales para altas rápidas.

**Cierre:** cada indicador tiene datos verificables y lleva al registro o filtro correcto; no hay métricas decorativas ni acciones sin implementación.

## Matriz de verificación obligatoria

Al implementar, inventariar todos los botones, enlaces y controles de cada ruta; cada uno debe tener caso, rol permitido, resultado esperado y evidencia. Esta matriz es el mínimo, no una afirmación de cobertura completa actual.

| Área | Casos que deben ejecutarse en escritorio y móvil |
| --- | --- |
| Sesión y navegación | Login, sesión caducada, logout, abrir/cerrar sidebar, atrás/adelante y navegación SPA |
| Páginas | Buscar, combinar filtros, ordenar, paginar, abrir acciones, crear, editar, preview, publicar y ver página pública |
| Editor | Campos, selects buscables, logo, colores, añadir/quitar/activar enlaces, ordenar, validación, guardar y recuperar copia |
| Clientes y locales | Alta, edición, relaciones opcionales, búsquedas, filtros y regreso al listado |
| Ventas | Crear, guardar borrador, confirmar, stock insuficiente, doble envío, abrir página, rechazar/cancelar conservando historial y navegar al retiro de acceso |
| Stock | Productos, compra/entrada, pago, reserva, producción, chips, ajustes, precio, entrega, cancelación, devolución física e historial; probar disponibilidad concurrente |
| Contabilidad y precios | Filtros, registro de operaciones y consultas disponibles; identificar vistas placeholder y evitar controles sin implementación |
| Publicaciones y sincronización | Buscar/filtrar, detalle, publicación individual, progreso, worker detenido, fallo, reintento, retirada, reactivación, publicación concurrente con rechazo y antigüedad de copia local |
| Sitio público | WhatsApp, llamada, correo, mapas, enlaces externos, copiar transferencia, acordeones y cualquier control exportado |

Revisión de disposición: 320, 390 y 430 px; tablet 768/1024 px; escritorio 1440 px. F12 puede ayudar a detectar desbordamientos y espaciado. Se omiten como requisito las suites «iPhone/Android» que solo emulen dispositivos o cambien el viewport; no acreditan la corrección del bug que el usuario únicamente observa en un teléfono real.

Validación del bug: primero en el móvil físico afectado, registrando navegador/SO y evidencia antes/después; ampliar a otro teléfono real si está disponible, sin imponer iPhone y Android a la vez. Comprobar toque, teclado virtual, compartir, apertura de enlaces y regreso de otra app. Si no hay dispositivo accesible, el resultado se declara pendiente de validación física. Las pruebas automatizadas de escritorio/servidor continúan cubriendo reglas y regresiones que sí puedan reproducir.

Condiciones: red normal, perfil 4G lento documentado, pérdida de red durante escritura, doble toque, sesión expirada, nombres largos, listado vacío, 50 registros y usuario sin permiso. Usar staging y datos aislados para compras/pagos/publicaciones. No publicar ni alterar registros reales para demostrar una interacción.

## Criterios de aceptación y entrega

- Cero bloqueos P0/P1 en los recorridos principales; todos los controles inventariados se ejecutan o explican por qué están deshabilitados.
- Sin scroll horizontal de página desde 320 px, acciones cortadas ni contenido tapado por teclado o barras fijas.
- Controles esenciales de 44 × 44 px, contraste de texto normal de al menos 4,5:1, foco visible, nombres accesibles y navegación por teclado.
- Mensaje visual de interacción en menos de 150 ms como objetivo de interfaz; confirmación remota medida aparte. No simular éxito para alcanzar una métrica.
- Capturas en los tamaños clave, trazas de los flujos y resultado de pruebas. Una suite omitida por credenciales ausentes se declara no ejecutada, nunca aprobada.
- Evidencia física del arreglo de «Abrir acciones», distinta de una captura F12; estado de acceso público separado de desactivación interna y de estado comercial de la venta.

Pruebas de servidor: seleccionar `AdminPanelTest`, `AdminUiTest`, `ResponsiveContentTablesTest`, `BusinessLedgerTest`, `StockPurchasesTest`, pruebas de venta/acciones y publicaciones según cambios. Añadir pruebas de comportamiento donde faltan; no duplicar asserts de HTML como sustituto de prueba táctil. Compilar con `npm run build:admin` y ejecutar las pruebas de navegador pertinentes con entorno aislado autenticado; revisar skips y no reportarlas como pruebas de un teléfono físico. Para PHP modificado, aplicar Pint según `src/AGENTS.md`.

## Orden, esfuerzo y dependencias

| Entrega | Esfuerzo orientativo | Dependencia |
| --- | --- | --- |
| A: diagnóstico y acciones | 1–2 jornadas | Acceso al panel de prueba y al móvil físico afectado |
| B: estados y acciones | 1–2 jornadas | A; contrato de estados acordado con plan 2 |
| C: editor y entrega | 2–3 jornadas | A/B |
| D: ventas y stock | 2–4 jornadas | A/B y datos aislados de negocio |
| E: Publicaciones y retirada | 2–3 jornadas | B, D y operaciones de publicación/retirada del plan 2 |
| F: inicio y continuidad | 1 jornada | D/E |

Total orientativo: 9–15 jornadas de una persona, sujeto a reproducción, infraestructura y dispositivos disponibles. Cada entrega se libera con sus pruebas; empezar por A/B y desarrollar D/E como flujos prioritarios. El front de Publicaciones se cuenta en E y no debe duplicarse al sumar esfuerzo con el plan 2; la retirada pública y su verificación técnica pertenecen al plan 2. Referencias: `PaginaResource.php`, `Ventas.php`, `StockTarjetas.php`, `PublicacionesSitio.php`, sus vistas Blade, `AdminPanelProvider.php`, componentes del editor, `theme.css` y `tests/Browser`.

Estas decisiones actualizan la dirección de los planes anteriores sin dar por implementadas sus fases. Reutilizar [las reglas de vistas](BASE_REGLAS_VISTAS_ADMIN.md) y [las normas gráficas](NORMAS_GRAFICAS_CLICKTAP.md); preservar los cambios locales existentes al ejecutar.

## Avance de implementación del 28 de octubre

La lista de Páginas ofrece Editar, vistas previas y Publicar como acciones directas, con objetivos táctiles más amplios; se eliminó el menú «Acciones» de esa lista. El editor y las tablas de inventario recibieron ajustes de ancho, vista previa y disposición móvil. El inicio enlaza Ventas, Stock y Publicaciones. La vista de Publicaciones ahora permite buscar, filtrar, publicar, retirar, reintentar y reactivar según estado. Ventas registra «Cliente no continúa» con motivo y conserva el estado comercial y la relación con la página.

La compilación del tema y las pruebas de servidor pasaron. La corrección táctil sigue pendiente de validación en el teléfono físico afectado; ningún viewport emulado la acredita. Los recorridos completos de pagos, devoluciones y stock en un móvil real también siguen pendientes. El panel continúa sirviéndose desde la instalación local, por lo que la operación con el PC apagado requiere alojamiento externo.
