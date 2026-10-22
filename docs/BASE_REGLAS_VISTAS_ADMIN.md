# ClickTap — Base de reglas para las vistas del administrador

**Referencia inicial:** vista **Clientes** (`/admin/clientes`), 22 de octubre de 2026.  
**Alcance:** panel administrativo Filament. Esta base complementa [las normas gráficas](NORMAS_GRAFICAS_CLICKTAP.md); no sustituye las reglas propias de cada módulo.

## 1. Estructura común

1. Toda vista usa el panel `admin` y su estructura compartida: sidebar negro, área de trabajo clara, logo oficial, tipografía Inter y grupos de navegación existentes. La vista define su grupo, icono, nombre y orden; no construye un segundo shell.
2. Hay un solo título principal visible. En una lista, el título va en la cabecera común. En crear, editar o ver un registro, la cabecera indica la operación y el contenido muestra un enlace «Volver a …» seguido del nombre del registro o del título de creación. Se reutilizan `header-title.blade.php` y `page-title.blade.php`.
3. El contenido se organiza en superficies blancas sobre `--ct-bg`. Se usan los colores y estados de `NORMAS_GRAFICAS_CLICKTAP.md`; el verde neón identifica acciones principales y navegación activa, con texto oscuro. Los estados de negocio usan texto y color semántico, nunca solo color.
4. Las etiquetas y ayudas se escriben en español directo. Los nombres de acciones indican el resultado: «Crear cliente», «Añadir contacto», «Editar datos». Las fechas se presentan en `America/Santiago` y los importes en CLP cuando corresponda.

## 2. Patrón para vistas de listado

1. La zona superior responde a tres preguntas: **qué se está viendo**, **cómo encontrar o acotar registros** y **cuál es la acción principal**. En Clientes esto se resuelve con título, búsqueda, botón «Filtros» y «Crear cliente». La disposición puede variar en módulos sin registros, pero esa jerarquía debe conservarse.
2. La búsqueda cubre los campos que una persona usaría para identificar un registro. En Clientes: nombre, contacto y teléfono. Cada filtro expresa una condición de negocio comprensible; si es binario, ofrece «Todos», «Con» y «Sin». Búsqueda y filtros deben poder combinarse.
3. Una tabla se usa si las columnas siguen siendo legibles; una cuadrícula de datos por registro sirve cuando hay textos largos o varios atributos. Clientes usa columnas; Locales y Páginas ya usan `ct-record-grid`. No se impone una tabla idéntica a todos los módulos.
4. La primera información de cada registro permite reconocerlo. Los datos secundarios tienen etiquetas o encabezados claros. Un valor ausente muestra una expresión útil, como «Sin contacto», «Sin teléfono» o «Sin asignar»; nunca un espacio ambiguo ni un dato ficticio.
5. Las acciones de fila son específicas del estado del registro. En Clientes, «Añadir contacto» sustituye «Editar» cuando faltan datos de contacto. Las acciones peligrosas o externas requieren el control correspondiente: permiso, confirmación y resultado visible.
6. La acción de crear se muestra solo cuando el usuario tiene permiso. Si se incorpora una barra personalizada, se acota al listado correspondiente mediante el hook de Filament; `clientes-toolbar.blade.php` está limitado a `ListClientes`. No se copia una barra global para cada recurso.
7. Se contemplan al menos tres estados: registros disponibles, resultado vacío por búsqueda o filtros, y ausencia total de registros. Cada uno debe orientar la siguiente acción sin prometer datos que todavía no existen.

## 3. Formularios y detalle

1. Los formularios agrupan campos por tarea, con etiqueta, tipo correcto, validación y ayuda cuando el dato pueda generar dudas. Los campos obligatorios son los mínimos necesarios; no se bloquea el alta por información que puede completarse después.
2. Las relaciones se eligen por nombre y permiten buscar cuando el catálogo puede crecer. Solo se permite crear una relación desde el formulario si el flujo y los permisos lo justifican, como en la selección de cliente desde Locales.
3. Se distingue el dato interno del público. Notas, contacto privado, cobros y metadatos operativos no aparecen en páginas públicas ni vistas previas.
4. Guardar, sincronizar y publicar son estados diferentes. La interfaz solo confirma una operación cuando su resultado se conoce; un fallo muestra una explicación y conserva el contexto para corregirlo.
5. La navegación de regreso mantiene un destino claro al listado del recurso. Los enlaces internos usan rutas de Filament o rutas nombradas, no direcciones construidas a mano.

## 4. Diseño adaptable y accesibilidad

1. Desde 320 px no hay desbordamiento horizontal ni contenido o acciones cortados. Los textos largos saltan de línea. La barra de búsqueda, filtros y acción principal se reordena en ancho reducido; no se ocultan acciones esenciales para hacer caber el diseño.
2. Se conserva foco visible y operación por teclado. Botones y controles principales apuntan a un área interactiva de al menos 44 px. Los controles tienen nombre accesible y las validaciones indican el campo afectado.
3. Los estados usan palabras además de color. No se usa texto blanco sobre `#B6FF00` ni verde neón como texto sobre superficie clara. Se respeta la preferencia de movimiento reducido.
4. El sidebar puede contraerse, pero sus nombres completos siguen disponibles. Las etiquetas largas se parten en varias líneas al expandirse.

## 5. Dónde implementar cada decisión

| Decisión | Lugar de referencia |
|---|---|
| Marca, navegación, tema y hooks compartidos | `src/app/Providers/Filament/AdminPanelProvider.php` |
| Tokens y estilos comunes | `src/resources/css/filament/admin/tokens.css` y `theme.css` |
| Título y regreso comunes | `src/resources/views/filament/components/page-title.blade.php` |
| Campos, columnas, búsqueda, filtros y acciones propios | `src/app/Filament/Resources/*Resource.php` |
| Comportamiento de una página de recurso | `src/app/Filament/Resources/*Resource/Pages/` |

Antes de añadir CSS o un componente, comprobar si la decisión ya existe en el panel. Una regla compartida se extrae cuando al menos dos vistas la necesitan y el comportamiento es realmente igual. Los estilos específicos quedan acotados a su vista para no modificar otras tablas o formularios.

## 6. Revisión mínima al crear una vista

- [ ] Nombre, grupo, icono y orden de navegación coherentes; un solo título principal.
- [ ] Acción principal visible y autorizada; regreso claro desde crear o editar.
- [ ] Identidad del registro, valores ausentes y estados expresados con texto real.
- [ ] Búsqueda y filtros responden a preguntas de negocio y funcionan juntos cuando existen.
- [ ] Contenido largo y controles utilizables en escritorio y a 320 px.
- [ ] Foco, contraste, etiquetas y mensajes de error comprobados.
- [ ] Prueba funcional estrecha para permisos, acción principal y filtros o estado más importante del módulo.

**Límite de esta base:** Clientes aporta el patrón de listado y formulario simple. Páginas, stock, contabilidad y publicaciones requieren reglas adicionales para sus estados, operaciones y datos; no se deben copiar las columnas o filtros de Clientes literalmente.
