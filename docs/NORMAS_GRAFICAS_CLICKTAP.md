# ClickTap — Normas gráficas y sistema visual del administrador

Versión 1.0 · 7 de octubre de 2026.

## 1. Alcance

Este documento reúne la identidad del kit ClickTap y las decisiones de UI aprobadas para el panel administrativo. Especifica colores, tipografía, componentes, medidas y comportamiento responsive para implementar el [prototipo de referencia](referencias/clicktap-base-ui-aprobado.html).

La paleta y los originales gráficos provienen del [kit de marca](marca/LEEME.md). Las dimensiones de interfaz provienen del CSS final del prototipo. Las pautas de accesibilidad y producción complementan esa referencia y se deben verificar al codificar. El kit no determina todavía una tipografía corporativa oficial ni un manual de impresión.

## 2. Identidad y logotipo

Nombre: **ClickTap**, respetando ambas mayúsculas. La composición principal conserva «Click» blanco, «Tap» verde neón y dos ondas de proximidad verdes sobre negro. No sustituir las ondas por símbolos nuevos.

| Archivo original | Dimensiones | Uso |
|---|---|---|
| [clicktap-horizontal.png](marca/clicktap-horizontal.png) | 2172 × 724 px, relación 3:1 | Identidad horizontal y login |
| [clicktap-presentacion.png](marca/clicktap-presentacion.png) | 1774 × 887 px, relación 2:1 | Referencia visual principal |
| [clicktap-icono.png](marca/clicktap-icono.png) | 1254 × 1254 px, relación 1:1 | Propuesta compacta T con dos ondas |

Los PNG tienen fondo negro integrado: no son transparentes ni vectoriales. Las variantes derivadas pueden diferir geométricamente del original. Mantener la relación de aspecto y el margen del archivo; no deformar, inclinar, recolorear, agregar brillo, degradados, sombras ni recortar las ondas. No interpretar los prompts históricos del kit como una orden para regenerar el logo.

El nombre tipográfico de la cabecera del prototipo es un sustituto de maquetación. Para reproducir exactamente la referencia puede conservarse durante la primera entrega; integrar el PNG definitivo exige comprobar su caja y legibilidad en los 186 px del sidebar. No reconstruir el logotipo con una fuente aproximada y llamarlo original. La vectorización y los favicons finales quedan como preparación de assets aparte.

La referencia contraída oculta la marca y conserva el control de despliegue, los iconos y el avatar. No reemplazar el avatar del usuario por el icono de ClickTap. Verificar cualquier favicon a 16 y 32 px antes de darlo por final. No hay todavía una medida mínima oficial de logotipo ni una equivalencia CMYK aprobada: no inventarlas.

## 3. Paleta de marca

| Uso | Color |
|---|---|
| Negro de marca | `#101010` |
| Blanco de marca | `#FFFFFF` |
| Verde neón de marca | `#B6FF00` |

El verde destaca acciones y selección; no debe llenar el área de trabajo. La proporción orientativa 55% blanco, 35% negro y 10% verde del kit no es una obligación matemática. La última preferencia aprobada requiere predominio de superficies blancas en el área de trabajo.

El admin usa tema claro explícito, incluso si el sistema está en modo oscuro. El sidebar permanece oscuro. No introducir un tema oscuro alternativo en esta entrega.

## 4. Tokens de superficie y contenido

| Token propuesto | Valor exacto | Aplicación |
|---|---|---|
| `--ct-bg` | `#F6F7F5` | Fondo del área de trabajo |
| `--ct-paper` | `#FFFFFF` | Tarjetas, campos y contenedor de secciones |
| `--ct-ink` | `#101010` | Texto principal |
| `--ct-muted` | `#596052` | Texto secundario sobre fondo claro |
| `--ct-line` | `#DCE0D5` | Separadores y bordes de contenedores |
| `--ct-soft` | `#EEF1E8` | Superficie neutral suave |
| `--ct-warning` | `#704811` | Texto del aviso sobre borradores |
| `--ct-warning-bg` | `#FFF8E8` | Fondo del aviso sobre borradores |
| `--ct-input-border` | `#8C9681` | Borde de campos |
| `--ct-focus` | `#375500` | Foco sobre fondo claro |
| `--ct-sidebar` | `#101010` | Fondo lateral |
| `--ct-sidebar-text` | `#D5DBCF` | Entradas laterales |
| `--ct-sidebar-group` | `#AAB2A0` | Grupos de navegación |
| `--ct-sidebar-divider` | `#34382F` | Separación del usuario |
| `--ct-sidebar-control` | `#242820` | Botón contraer/desplegar |
| `--ct-sidebar-control-border` | `#41483A` | Borde del control lateral |
| `--ct-user-avatar-bg` | `#29341C` | Fondo del avatar de ejemplo |
| `--ct-user-avatar-text` | `#C8FF75` | Iniciales del avatar de ejemplo |
| `--ct-user-secondary` | `#B9C4AD` | Texto «Sesión activa» |

Los nombres de tokens adicionales son la traducción propuesta de colores literales del prototipo. Centralizar los valores en la implementación; no repetir hexadecimales independientes por pantalla.

## 5. Estados semánticos

| Tono | Texto/indicador | Fondo | Estados |
|---|---|---|---|
| Éxito | `#166534` | `#EAF7EE` | Publicada, vigente, lista para entregar, enlaces activos |
| Información | `#1E40AF` | `#EDF3FF` | Guardado, en preparación, trabajo en curso |
| Pendiente | `#92400E` | `#FFF3DC` | Cambios sin guardar, revisión pendiente, entrega/cobro pendiente |
| Inactivo | `#475569` | `#F1F5F9` | Sin trabajo activo, sin publicación confirmada |
| Error | `#991B1B` | `#FEF2F2` | Guardado fallido, publicación fallida, validación |

Usar etiquetas textuales junto al color; el punto circular refuerza, no reemplaza el texto. El verde neón de marca no sustituye al verde semántico de éxito.

Una etiqueta usa texto de 12 px, peso 600, interlineado 1.5, padding 5 px vertical y 9 px horizontal, radio 6 px, separación interna 6 px e indicador de 6 × 6 px. No estirarla a todo el ancho. La aprobación puede figurar debajo del estado del borrador sin mezclarse con la visibilidad pública.

Guardar correctamente cambia el borrador a azul; editarlo cambia a ámbar. La versión pública conserva su estado hasta una confirmación real. «Sin trabajo activo» es gris, no una advertencia. Un resultado remoto desconocido se presenta como pendiente de comprobación, no como éxito.

## 6. Tipografía

Stack de la referencia: `Inter, system-ui, sans-serif`. El prototipo no descarga Inter; el navegador puede mostrar el fallback del sistema. Para una comparación exacta, verificar la fuente realmente renderizada y fijar una fuente local común a la referencia y la aplicación. No cambiar de tipografía durante una comparación visual.

| Elemento | Tamaño | Peso | Observación |
|---|---|---|---|
| Texto base | 14 px | 400 | Interlineado 1.5 |
| Título de local | 26 px | 700 | Interlineado 1.2; tracking −0.7 px |
| Título de local móvil | 23 px | 700 | Hasta 620 px |
| Título de sección | 16 px | 650 | Margen inferior 4 px |
| Etiqueta de campo | 13 px | 550 | Texto visible, no solo placeholder |
| Texto de campo | 16 px | 400 | Legible en móvil |
| Botón | 14 px | 600 | Etiqueta de acción concreta |
| Estado | 12 px | 600 | Tono semántico |
| Metadato/ayuda | 12 px | 400 | Color secundario |
| Grupo lateral | 11 px | 400 | Mayúsculas; tracking 1.2 px |
| Nombre de usuario | 12 px | 600 | Blanco sobre sidebar |
| Estado de sesión | 11 px | 400 | Texto secundario lateral |

Para reproducir pesos 550/650, servir una fuente variable con esos pesos. Evitar redondearlos silenciosamente a otros valores. No emplear el estilo del logotipo en párrafos, tablas o formularios.

## 7. Geometría aprobada

Las medidas son píxeles CSS y representan el estado predeterminado guardado del prototipo, sin ajustes de los controles del chat.

| Elemento | Medida |
|---|---|
| Sidebar desplegado / contraído | 186 / 72 px |
| Padding sidebar escritorio | 26 px vertical; 16 px horizontal |
| Padding sidebar hasta 880 px | 20 px vertical; 14 px horizontal |
| Padding sidebar contraído | 24 px vertical; 14 px horizontal |
| Separación de regiones del sidebar | 22 px |
| Control lateral / avatar de usuario | 44 × 44 px |
| Padding área principal | 22 px; 16 px hasta 620 px |
| Separación después de título y estados | 22 px |
| Tarjetas de estado | 3 columnas iguales; gap 10 px; padding 14 px |
| Radios de tarjetas y contenedor principal | 12 px |
| Borde de contenedores | 1 px |
| Barra de pestañas | Padding 8 px arriba y 20 px horizontal; sin margen exterior inferior |
| Cada pestaña | Padding 12 px vertical / 10 px horizontal; gap entre pestañas 6 px |
| Indicador de pestaña activa | Borde inferior verde neón de 3 px |
| Contenido del contenedor común | Padding 24 px; gap 20 px |
| Botones | Alto mínimo 44 px; padding 10 / 14 px; radio 8 px |
| Campos | Padding 10 / 12 px; radio 7 px; margen superior 6 px |
| Separación entre campos | 16 px |
| Textarea | Alto mínimo 78 px; ampliable verticalmente |
| Aviso de borrador | Padding 12 / 14 px; radio 8 px; margen inferior 20 px |
| Filas de detalle | Padding vertical 16 px; separador inferior de 1 px |
| Iconos de menú y botones | 16 × 16 px |
| Icono de contraer | 18 × 18 px |
| Icono de «Ver página» | 13 × 13 px |

Usar la escala general 4, 8, 12, 16, 24 y 32 px en futuras extensiones. Las medidas específicas de la tabla (22, 14, 10, etc.) prevalecen para reproducir esta pantalla. No «normalizar» todo a otra escala y alterar su densidad.

El alto mínimo de 660 px del prototipo es un tamaño de demostración, no una altura fija de producción. La aplicación debe crecer con el contenido y la ventana sin recortar acciones.

## 8. Composición y navegación

Orden del menú:

1. Inicio: Resumen.
2. Negocios: Clientes, Locales, Páginas.
3. Operación: Placas, Solicitudes.
4. Finanzas: Cobros.
5. Sistema: Publicaciones, Historial, Configuración.

La selección lateral es verde neón con texto e icono negro. Los grupos son texto secundario en mayúsculas. El pie pertenece al usuario: avatar, nombre y sesión. No contiene mensajes promocionales ni «Panel interno / ClickTap».

En escritorio contraído se ocultan marca, grupos, etiquetas y detalles del usuario; quedan iconos, avatar y control de expansión. Mantener nombres accesibles y ayudas de texto. La preferencia puede persistirse localmente sin guardar datos privados del negocio.

La ficha contiene, en orden: título y vista previa; resumen de tres estados; contenedor de secciones. El contenedor blanco incluye tanto las pestañas como el contenido. Los paneles interiores no añaden otro borde ni otra tarjeta. No separar nuevamente la barra de pestañas como en la versión inicial.

No mostrar la barra «Negocios / Locales / Café Aurora — Administrador», el subtítulo del cliente ni la frase «Todo el trabajo de este local, en un lugar».

## 9. Controles y enlaces

- Botón principal: verde neón y texto negro; para la ficha, «Guardar borrador».
- Botón secundario: blanco, borde neutral y texto oscuro; «Vista previa».
- Hover: oscurecimiento leve equivalente a `brightness(.96)`; transición de 150 ms si no hay preferencia de movimiento reducido.
- Foco: contorno visible de 3 px y separación 3 px; verde oscuro sobre claro y neón sobre sidebar.
- Deshabilitado: estado visual atenuado y razón comprensible cuando sea necesaria; nunca confundirlo con un error.
- «Ver página»: enlace verde oscuro, subrayado, icono diagonal y aviso accesible de nueva pestaña.

Destino del enlace: URL pública asignada al local del cliente; si no hay, `https://clicktap.app/`. Es independiente de «Vista previa» y del estado de publicación. No mostrar contenido de ejemplo en lugar de abrir la dirección. Validar la URL al integrar datos.

Para producción, mejorar la semántica de las pestañas con nombres, asociación entre controles y paneles y operación de teclado coherente. El color y el subrayado permanecen iguales aunque cambien los atributos de accesibilidad respecto del prototipo.

## 10. Vista previa y marca del cliente

La vista previa aparece dentro del contenedor, a la derecha del contenido en anchos mayores a 880 px, con columnas `minmax(0, 1fr)` y `minmax(210px, .7fr)`. Hasta 880 px se apila debajo.

Marco de ejemplo: ancho máximo 300 px, borde oscuro de 5 px, radio 26 px, padding 28 / 18 px. El panel exterior de preview tiene padding 16 px y radio 12 px. La integración final debe usar la misma plantilla Astro y representar el borrador guardado con acceso autorizado.

Los colores crema/café de Café Aurora pertenecen al negocio ficticio. No son tokens corporativos de ClickTap y no deben imponerse a otros clientes. Nombre, descripción y enlaces de ejemplo se sustituyen por contenido público autorizado; contactos privados, cobros y notas no aparecen ahí.

## 11. Responsive

| Ancho | Comportamiento |
|---|---|
| Más de 880 px | Sidebar lateral; preview junto al editor cuando esté abierto |
| 621–880 px | Sidebar lateral; preview debajo; navegación contraíble disponible |
| Hasta 620 px | Sidebar inicialmente oculto y desplegable; título con botón de menú; estados en una columna |

En móvil: padding principal 16 px, contenido 18 px vertical / 16 px horizontal, pestañas con padding 10 / 8 px y barra 6 px superior / 8 px horizontal. Las pestañas se distribuyen en varias líneas si es necesario. El botón de guardado ocupa todo el ancho disponible. En la referencia móvil, el menú abierto se incorpora al flujo sobre el contenido, no como un overlay nuevo.

Mantener soporte hasta 320 px sin desbordamiento horizontal. Permitir salto de nombres largos y filas de datos. No reducir el texto para ocultar problemas de distribución ni fijar alturas que corten contenido.

## 12. Accesibilidad y lenguaje

Objetivo de implementación: texto normal con contraste mínimo 4.5:1, texto grande 3:1; verificar además foco y límites de controles. El negro `#101010` sobre el neón alcanza aproximadamente 15.67:1; blanco sobre neón solo 1.21:1, por lo que no se permite esa combinación para texto funcional. [Referencia de contraste WCAG](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).

Los controles principales tienen al menos 44 px de alto. «Ver página» mide 32 px en la referencia de escritorio: ampliar su área interactiva a 44 px con puntero táctil sin comprimir otros controles. Estas son metas de diseño, no una declaración de certificación de accesibilidad.

Mantener foco visible, nombres de botones, etiquetas asociadas a campos, mensajes de error junto al origen y avisos de guardado accesibles. Evitar depender de hover, color o animación. Respetar movimiento reducido.

Usar español directo: «Guardar borrador», «Vista previa», «Ver página», «Cambios sin guardar». No decir «Publicado» cuando solo se guardó o envió un despliegue. Fechas en `America/Santiago`; importes en CLP sin decimales. El nombre mostrado en el pie corresponde al usuario real, no al cliente propietario del local.

## 13. Usos incorrectos

- Poner texto blanco sobre verde neón.
- Hacer verde neón cualquier estado de éxito o cada tarjeta del dashboard.
- Convertir la superficie de trabajo al modo oscuro del sistema en esta versión.
- Separar pestañas y contenido en cajas independientes.
- Volver a introducir los textos que el usuario eliminó.
- Sustituir el avatar de usuario por la marca.
- Mezclar iconos de familias diferentes o cargarlos desde el entorno del chat.
- Estirar logos, cambiar sus ondas o atribuirles transparencia inexistente.
- Usar la página pública como vista previa privada del borrador.
- Presentar fechas, cobros o versiones ficticias como datos reales.

## 14. Control de cambios

Esta versión incorpora las correcciones aprobadas en la conversación. Cualquier modificación posterior debe actualizar las normas, el plan y una nueva referencia identificable. Conservar el original aprobado; no sustituirlo silenciosamente para hacer coincidir una implementación divergente.

Para ejecutar estas normas, seguir el [plan de implementación](PLAN_IMPLEMENTACION_UI_MVP.md). La reproducción inicial cubre la ficha del local y la estructura común; login, listas generales y dashboard reutilizarán la identidad, pero requieren su propia revisión de composición.
