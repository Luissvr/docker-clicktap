# ClickTap — Plan de implementación de la interfaz del MVP

Fecha: 8 de octubre de 2026. Estado: fases 1–3 completadas y comprobadas; fases 4–7 pendientes.

## Hoja de ruta por fases

La entrega se divide en seis fases para construir y validar la base visual, seguidas de una fase funcional dividida por módulos. Cada fase produce un resultado revisable; se avanza cuando cumple su criterio de cierre. La aprobación del diseño vigente permite desarrollar la base sin volver a decidir sus colores o distribución.

| Fase | Objetivo | Entregable / criterio de cierre | Depende de | Estado |
|---|---|---|---|---|
| 1. Preparación | Fijar referencia y comprobar entorno | Referencia reproducible y entorno listo para desarrollar | — | Completada; captura renderizada a 1024 px y entorno Docker documentado al 8 oct. 2026 |
| 2. Sistema visual | Traducir las normas a estilos reutilizables | Tema compilable con colores, fuente, iconos y medidas definidos | 1 | Completada; build general y tema Filament 3 compilados al 8 oct. 2026 |
| 3. Estructura y navegación | Construir el marco del admin | Sidebar desplegable/contraíble, navegación y usuario; adaptación móvil | 2 | Completada; pruebas de rutas, navegador, sesión, escritorio y móvil al 8 oct. 2026 |
| 4. Ficha del local | Reproducir la pantalla aprobada | Estados, pestañas y contenido dentro del mismo panel blanco | 3 | Pendiente |
| 5. Interacciones | Completar el comportamiento de la interfaz | Cambios de sección, formulario, estados, preview y enlace/respaldo verificables con datos de prueba | 4 | Pendiente |
| 6. Validación visual | Comprobar fidelidad y facilidad de uso | Comparación con referencia, revisión responsive y accesibilidad sin bloqueos | 5 | Pendiente |
| 7. Funciones del MVP | Conectar los módulos a datos y procesos reales | Entregas funcionales incrementales según las subfases 7A–7E | 6 | Posterior a la base visual |

**Primer hito: al cerrar la fase 3**, el admin ya tiene la identidad de ClickTap y su estructura de navegación.

**Segundo hito: al cerrar la fase 6**, la base visual aprobada está codificada y validada. Este es el objetivo inmediato solicitado antes de implementar las funciones del negocio.

**Tercer hito: al cerrar la fase 7**, los módulos del MVP funcionan con persistencia, reglas de negocio y publicación verificadas. Las cuentas de clientes siguen fuera del alcance.

Las fases 1–6 usan datos ficticios aislados para los módulos todavía no implementados. Se reutiliza la autenticación existente y el usuario real del sistema. No se agregan cobros, despliegues ni operaciones reales de negocio para demostrar el diseño. La duración se estima al comprobar el entorno y cada entrega; este plan no fija fechas sin esa evidencia.

## 1. Objetivo y definición de fidelidad

Codificar la interfaz aprobada en Laravel/Filament, preservando su composición, colores, medidas y comportamientos. Construir primero la base visual con datos de demostración aislados; conectar después las funciones y los datos reales.

La referencia congelada es [clicktap-base-ui-aprobado.html](referencias/clicktap-base-ui-aprobado.html). Es una copia literal del último prototipo aprobado, no una aplicación terminada ni un documento HTML autónomo. Contiene dependencias del entorno de visualización que deben sustituirse al implementarlo.

SHA-256 de la referencia: `E2AC33E1D4F3241D59C8BDC8474C9643595B789799A4FC32ACA33044E4A637A7`.

Las [normas gráficas](NORMAS_GRAFICAS_CLICKTAP.md) documentan sus valores. El [kit original](marca/LEEME.md) y sus PNG están conservados en `docs/marca/` sin modificaciones.

«Exactamente» significa mantener la geometría, la jerarquía, los textos de acciones y la interacción aprobada, comparando referencia e implementación con el mismo navegador, fuente, dimensiones y datos. No significa copiar defectos de accesibilidad, dependencias del chat ni datos ficticios a producción. Las pantallas aún no diseñadas deben reutilizar esta base; no se consideran aprobadas al detalle.

## 2. Decisiones que ya están cerradas

- Área de trabajo clara, tarjetas y contenedor principal blancos; sidebar negro y acento verde neón.
- Sidebar de 186 px desplegado y 72 px contraído en escritorio. En móvil se muestra u oculta mediante un control junto al título.
- Pie del sidebar con avatar, nombre del usuario autenticado y sesión activa. Contraído conserva el avatar.
- Título del local y botón «Vista previa». Sin barra superior adicional de navegación/administrador.
- Tres bloques: página pública, borrador y trabajo de publicación; los estados siguen siendo independientes.
- Un solo contenedor agrupa Página, Placas, Solicitudes, Cobros e Historial y el contenido seleccionado. No son filtros de una tabla: son secciones del local.
- Colores semánticos: verde para publicado/vigente; azul para guardado/preparación; ámbar para pendientes/cambios sin guardar; gris para inactividad; rojo para errores.
- «Ver página» abre en otra pestaña la dirección asignada al negocio correspondiente al local actual. Si no hay una dirección, abre `https://clicktap.app/`.
- «Vista previa» muestra el borrador guardado y no sustituye «Ver página».
- No volver a introducir «Cliente · Aurora SpA», «Todo el trabajo de este local, en un lugar», «Negocios / Locales / Café Aurora — Administrador» ni el pie «Panel interno / ClickTap».

La última regla del enlace prevalece sobre la primera propuesta de esconderlo cuando no hubiera una publicación. Debe poder usarse el respaldo oficial sin URL asignada; esto no convierte el estado de la página en «Publicada».

## 3. Estado técnico observado

| Elemento | Evidencia en el repositorio | Consecuencia |
|---|---|---|
| Laravel | `src/composer.lock`: 13.35.0 | Tomar manifiesto y lock como referencia; el README menciona Laravel 11 y está desactualizado |
| Filament | Lock: 3.3.56 | Implementar contra la API 3.x; no actualizar de versión mayor como parte implícita del diseño |
| Livewire | Lock: 3.8.10 | Reutilizar el mecanismo de interacción del panel |
| Estilos generales | Tailwind 4, Vite 8 en `src/package.json` | El tema Filament 3 requiere una compilación compatible separada con Tailwind 3 |
| Panel | `src/app/Providers/Filament/AdminPanelProvider.php` | Configuración base ámbar, dashboard y widgets predeterminados; aún no equivale al prototipo |
| Página pública | Migración `paginas`: `local_id`, `slug`, referencias de publicación y suspensión | No existe un campo de URL completa en esa migración; definir el mapeo antes de conectar «Ver página» |
| Entorno | Docker Compose: PHP, Nginx, PostgreSQL | Verificar versiones y dependencias efectivas dentro de Docker antes de ejecutar cambios PHP |

La inspección documental no acredita que los contenedores o el build estén funcionando. Antes de modificar `src`, leer y cumplir su `AGENTS.md`, incluidas las instrucciones aplicables de preparación. La documentación de este plan no instala dependencias ni modifica la aplicación.

## 4. Estrategia de implementación

Mantener Laravel + Filament + Livewire; usar Blade y Alpine para composición e interacciones locales. Mantener la autenticación, el middleware, formularios y validaciones de Filament. No agregar React/Angular para reproducir esta interfaz.

Usar el tema y las opciones de configuración de Filament para acercar primero el panel a la referencia. Cuando una región no permita conseguir la composición exacta, crear una vista/componente acotado para esa región. No modificar `vendor`, los CSS publicados del proveedor ni reemplazar el panel completo por HTML desconectado de su ciclo de vida.

Planificar una compilación de tema con Tailwind 3 aislada del Tailwind 4 general: herramienta y versión resueltas en un lock, entradas/salidas propias y un comando reproducible de build. Registrar el CSS resultante en el panel. No asumir que el tema Filament 3 compila con el plugin Vite de Tailwind 4. La estrategia de compilación separada está contemplada en la [documentación de temas](https://filamentphp.com/docs/3.x/panels/themes).

Configurar explícitamente el tema claro del admin y sus colores. El verde exacto de los botones y el texto oscuro deben venir de tokens; una paleta generada por Filament por sí sola no garantiza el resultado exacto. Servir fuente e iconos localmente para evitar que la interfaz dependa de una conexión externa.

## 5. Estructura propuesta del código

Estas rutas son un mapa de trabajo; no se han creado clases ni componentes aún.

| Ruta propuesta, relativa al repositorio | Responsabilidad |
|---|---|
| `src/resources/css/filament/admin/tokens.css` | Valores de marca, estados, medidas y tipografía |
| `src/resources/css/filament/admin/theme.css` | Composición del panel y ajustes acotados de Filament |
| `src/resources/css/filament/admin/tailwind.config.js` | Configuración exclusiva y compatible del tema |
| `src/resources/views/components/clicktap/` | Estado, resumen de publicación, grupo de pestañas, usuario y región de vista previa |
| `src/resources/views/filament/resources/local-resource/pages/workspace.blade.php` | Composición de la ficha aprobada |
| `src/app/Filament/Resources/LocalResource.php` y sus páginas | Entrada autenticada al local y navegación |
| `src/app/ViewModels/LocalWorkspaceViewModel.php` | Contrato de datos de presentación, independiente de datos ficticios |
| `src/app/Services/PublicPageUrlResolver.php` | Resolver dirección asignada o respaldo oficial |
| `src/config/clicktap.php` | Dirección oficial y, si corresponde, base pública distinta de `APP_URL` |
| `src/public/fonts/`, `src/public/brand/` | Recursos finales locales; no copiar todos los PNG grandes automáticamente |
| `src/tests/Feature/` y pruebas de navegador existentes o elegidas | Autorización, reglas de interacción y comparación visual |

Usar los mecanismos soportados de [configuración](https://filamentphp.com/docs/3.x/panels/configuration) y [navegación](https://filamentphp.com/docs/3.x/panels/navigation) como punto de partida. Confirmar nombres y puntos de extensión contra la versión instalada antes de escribir código.

## 6. Fases y tareas verificables

### Fase 1. Preparación y referencia

Objetivo: disponer de una referencia estable y un entorno reproducible. Sin dependencia de otras fases. La documentación ya está conservada; las tareas técnicas siguen pendientes.

- [x] Conservar el prototipo aprobado y los originales de marca en el repositorio.
- [x] Documentar tokens, componentes y reglas que no deben cambiar.
- [x] Inspeccionar entorno, dependencias y reglas del repositorio antes de implementar; comprobar Docker, PHP 8.3, Composer 2 y Node 24.
- [x] Preparar rutas de demostración exclusivamente para los entornos `local`/`testing`, detrás del inicio de sesión existente y aisladas de producción.
- [x] Capturar la referencia aprobada a 1024 px con la misma fuente Inter local usada por el panel: [captura de referencia](referencias/clicktap-base-ui-aprobado-renderizado-1024.png). Se conserva el HTML original sin modificar.

Salida: baseline visual reproducible y entorno listo. La referencia inicial tuvo comprobación estática; no afirmar que ya existe una validación visual de navegador completa.

### Fase 2. Sistema visual

Objetivo: convertir las normas gráficas en una base reutilizable de estilos. Comienza con la fase 1 cerrada.

- [x] Crear tokens a partir de las normas gráficas, manteniendo sus valores exactos.
- [x] Preparar el build separado del tema Filament 3 con Tailwind 3 y cargarlo en el panel.
- [x] Fijar el modo claro y los estilos oscuros del sidebar.
- [x] Servir Inter, SVG de Lucide y Alpine localmente, sin dependencias visuales de CDN.
- [x] Confirmar el build general de Vite/Tailwind 4 más el tema Filament 3; el frontend público conserva sus estilos por separado.

Salida: build reproducible, sin errores de recursos; colores y tipografía coinciden con la referencia.

### Fase 3. Estructura y navegación

Objetivo: construir el marco común que usarán todas las pantallas. Depende del tema de la fase 2.

- [x] Aplicar anchos, márgenes, fondo y alineaciones del prototipo (sidebar de 186/72 px y puntos de cambio a 880/620 px).
- [x] Construir los grupos y destinos de navegación en el orden aprobado; mostrar cada módulo futuro como demostración en desarrollo.
- [x] Implementar expansión/contracción, nombres de teclado y preferencia local persistida.
- [x] Implementar apertura/cierre en móvil en el flujo del documento y mantener disponible el control de menú.
- [x] Mostrar nombre e iniciales/foto del usuario autenticado y mantener su avatar en modo contraído.
- [x] Conservar el cierre de sesión existente de Filament sin introducir cuentas o roles.
- [x] Eliminar la topbar y migas redundantes sin dejar un hueco equivalente.

Salida: navegación funcional y geometría comprobada mediante build, 16 casos de PHPUnit, un recorrido de Edge en escritorio/móvil y capturas guardadas junto a la referencia.

El cierre de las fases 1–3 se verificó el 8 de octubre de 2026: los temas general y del admin compilan; el panel móvil y de escritorio conserva su navegación y preferencia; el demo requiere usuario activo y solo existe fuera de producción. Evidencia: [captura de referencia 1024 px](referencias/clicktap-base-ui-aprobado-renderizado-1024.png), [captura del admin 1024 px](referencias/clicktap-panel-fase-3-1024.png) y [captura móvil 390 px](referencias/clicktap-panel-fase-3-movil-390.png). Las fases 4–7 siguen pendientes.

### Fase 4. Ficha del local

Objetivo: reproducir la pantalla central del prototipo con datos de prueba. Depende de la estructura de la fase 3.

- [ ] Componer título, «Vista previa» y tres tarjetas de estado.
- [ ] Crear un componente de estado con texto, tono e indicador; no distribuir colores sueltos por las vistas.
- [ ] Agrupar pestañas y sección seleccionada en un contenedor blanco con un único borde exterior.
- [ ] Reproducir formulario, aviso, resumen de enlaces y área de guardado.
- [ ] Reproducir contenido de Placas, Solicitudes, Cobros e Historial con fixtures locales.
- [ ] Mantener los valores introducidos al cambiar de pestaña y abrir/cerrar la vista previa.
- [ ] Eliminar del código de producción `window.openai`, `Tweak`, clases del entorno de visualización y el rótulo de demostración. El prototipo congelado conserva su contenido original.

Salida: primera entrega de base visual fiel, todavía sin publicación, cobros ni cambios reales de negocio. No ampliar el editor ni diseñar dashboards nuevos en esta entrega.

### Fase 5. Interacciones y contrato de datos

Objetivo: completar y verificar el comportamiento del diseño sin implementar aún los procesos del negocio. Depende de la ficha de la fase 4.

- [ ] Definir el ViewModel con: nombre del local, usuario, página pública y última confirmación, borrador/revisión, trabajo de publicación, URL asignada/resuelta y datos de cada pestaña.
- [ ] Separar estado local de UI de estado persistido del negocio. La preferencia del sidebar puede ser local; una publicación no se deduce del navegador.
- [ ] Mapear guardado azul, cambios sin guardar ámbar y fallo rojo con escenarios controlados de demostración. Dejar definido que, al conectar persistencia en la fase 7B, «Guardado» requiere confirmación del servidor.
- [ ] Implementar el resolutor de «Ver página» de la sección 7 y comprobar URL asignada/respaldo con entradas de prueba. La relación con la base de datos se conecta en la fase 7A.
- [ ] Mantener la vista previa de demostración consistente con la revisión guardada en la prueba; definir su contrato de datos para la integración privada con Astro de la fase 7C.
- [ ] Contemplar carga, vacío, error, sesión vencida, revisión desactualizada y desconexión.

Salida: componentes con entradas claras, sin estados de publicación simulados presentados como reales fuera de la demostración.

### Fase 6. Validación y cierre de la base visual

Objetivo: comprobar que el diseño codificado reproduce la referencia y se puede utilizar sin bloqueos. Depende de las interacciones de la fase 5. Las comprobaciones básicas se realizan durante cada fase; aquí se valida el conjunto.

- [ ] Comparar referencia e implementación con datos, viewport, zoom y fuente idénticos.
- [ ] Revisar a 1024 px: sidebar 186/72 px, panel principal, agrupación de pestañas, colores, tipografía, radios e iconos.
- [ ] Revisar también 320, 360, 620, 621, 768, 880, 881 y 1440 px, especialmente los cambios de distribución.
- [ ] Comprobar teclado, foco, etiquetas, contraste, zoom al 200% y movimiento reducido.
- [ ] Probar nombre largo de usuario/local y cantidades extensas sin solapamientos.
- [ ] Corregir diferencias y guardar capturas de comparación. No aprobar capturas de referencia nuevas automáticamente si cambian el diseño.

Salida: base visual validada y lista para conectar funciones. Ninguna desviación de estructura, color, medidas o texto aprobados queda sin explicar; las diferencias de rasterizado de texto se evalúan aparte.

### Fase 7. Conexión funcional del MVP

Objetivo: sustituir las fuentes ficticias por datos persistidos y procesos reales manteniendo los componentes validados. Empieza después del cierre de la base visual en la fase 6.

| Subfase | Trabajo | Criterio de cierre |
|---|---|---|
| 7A. Clientes y locales | Crear/buscar/editar clientes y locales, sus relaciones y página inicial; conectar datos de la ficha y destino de «Ver página» | Un local conserva sus datos al recargar y abre su propia URL o el respaldo oficial; no usa la página de otro local |
| 7B. Editor y borradores | Persistir textos, contactos públicos, imágenes, colores y enlaces; validar cambios y detectar revisiones desactualizadas | Guardar conserva el borrador sin cambiar la página pública; errores y conflictos no sobrescriben datos silenciosamente |
| 7C. Vista previa y revisión | Integración privada con la plantilla Astro; registrar revisión/aprobación conforme a la política que se defina | La vista previa representa la revisión guardada y solo es accesible por usuarios autorizados |
| 7D. Publicación | Exportación de contenido aprobado, seguimiento, confirmación, suspensión/reactivación y recuperación | La interfaz solo confirma publicación tras verificar producción; controla errores y resultados desconocidos |
| 7E. Operación y cierre del MVP | Placas, solicitudes, cobros, configuración, resumen e historial; respaldo y recuperación | Se cumplen las dependencias del flujo, las correcciones conservan historial y el respaldo/recuperación se han comprobado antes del uso real |

El orden es 7A → 7B → 7C → 7D → 7E. El historial se incorpora con cada operación que lo necesita; el resumen se alimenta conforme existan datos. El respaldo y la recuperación deben prepararse durante la fase funcional y verificarse antes de introducir datos reales, no posponerse hasta después de operar.

Conservar el orden del alcance: clientes/locales → editor y borradores → vista previa privada Astro → publicación y confirmación → placas, solicitudes y cobros. El diseño no resuelve la política comercial de aprobación, pagos o suspensión. Verificar autorización y reglas en el servidor; habilitar botones no reemplaza esas reglas.

## Registro de cierre de cada fase

Al terminar una fase, actualizar su estado en la hoja de ruta y registrar en la entrega:

- Resultado visible y archivos modificados.
- Criterios de cierre comprobados y evidencia pertinente.
- Limitaciones o decisiones pendientes que afecten a la siguiente fase.
- Siguiente fase habilitada, o condición concreta que falta por resolver.

No marcar una fase completada solo por tener sus pantallas dibujadas. La documentación y el prototipo aprobados no acreditan que el código de la aplicación esté implementado.

## 7. Regla exacta de «Ver página»

Resolver en el servidor la página del local actual mediante su relación con el cliente. Un cliente puede tener varios locales: no tomar la URL de otro local ni «la primera página del cliente».

La tabla actual guarda `paginas.slug`, no una URL completa. Antes de conectar el enlace, verificar el contrato de rutas de Astro. Si la URL se construye con una base pública y el slug, usar ese contrato real. Si el producto necesita una URL completa asignada, agregar un campo mediante una migración explícita. No asumir `https://clicktap.app/{slug}` sin comprobar la ruta pública.

| Datos disponibles | Destino |
|---|---|
| URL pública válida asignada/resuelta para el local | Esa dirección |
| Sin página relacionada, URL nula, vacía o solo espacios | `https://clicktap.app/` |
| Dirección malformada o esquema no admitido | Respaldo oficial; además validar/corregir el registro al guardar |

El enlace usa `target="_blank"`, `rel="noopener noreferrer"` y anuncia que abre otra pestaña. Admitir esquemas HTTP/HTTPS, preferir HTTPS en producción y no construir destinos desde campos privados. No consultar sitios externos al renderizar la ficha. La resolución del enlace no prueba que el sitio exista ni cambia la visibilidad confirmada.

«Vista previa» es otro control: abre contenido privado del borrador guardado y no debe dirigir al sitio oficial cuando falte la integración. Mostrar su estado de disponibilidad con claridad.

## 8. Validación funcional mínima al implementar

Pruebas concentradas en comportamiento, no en repetir cada clase CSS:

1. Usuario sin sesión no puede acceder al local ni al borrador privado.
2. Con URL asignada se respeta esa URL; sin URL o con valor inválido se usa el respaldo. Cubrir cliente con varios locales.
3. Contraer/desplegar y cambiar de ancho conservan acceso al menú y al avatar.
4. Cambiar de sección y abrir vista previa no descartan el formulario.
5. Un error de guardado mantiene las entradas y no muestra «Guardado».
6. Guardar no cambia la versión pública; una publicación solo se confirma con evidencia del servidor.
7. Los estados se entienden sin color y las interacciones son operables con teclado.

Ejecutar build del tema, checks existentes pertinentes y estas pruebas al introducir sus comportamientos. No ejecutar despliegues reales a Cloudflare para validar estilos ni resetear la base de datos del usuario.

## 9. Entregables y definición de terminado

- Tema compilable y tokens reutilizables.
- Sidebar responsive y espacio de usuario integrados en Filament.
- Ficha de local fiel a la referencia, con cinco secciones en un único contenedor.
- Estados semánticos, enlace público/respaldo y contrato de datos definidos.
- Demostración aislada de producción y sin dependencia del entorno del chat.
- Evidencia visual y pruebas pertinentes satisfactorias.
- Lista explícita de funciones pendientes; la base de UI no se presenta como un MVP funcional completo.

## 10. Fuentes y precedencia

1. Correcciones y aprobación expresas del usuario en esta conversación, incorporadas en la sección 2.
2. Prototipo congelado y normas gráficas de este directorio.
3. Documento funcional del usuario: `C:/Users/vilun/OneDrive/Desktop/Proyectos GIT/Linktree/Doc/funcionalidades-panel-admin-clicktap.md` (consultado el 7 de octubre de 2026; describe alcance, no implementación).
4. Manifiestos, migraciones y provider inspeccionados en este repositorio.
5. Documentación oficial de Filament citada arriba.

Las medidas reflejan el CSS guardado en el prototipo, con sus valores predeterminados. No existe evidencia de ajustes persistidos mediante los controles de diseño del chat; si una captura aprobada difiere, compararla antes de modificar la referencia.
