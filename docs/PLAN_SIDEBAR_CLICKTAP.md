# ClickTap — Plan de Diseño e Implementación del Sidebar Negro y Navegación Multilínea

**Fecha:** 21 de octubre de 2026  
**Estado:** Propuesta técnica y de diseño basada en [`docs/NORMAS_GRAFICAS_CLICKTAP.md`](file:///c:/Users/vilun/OneDrive/Desktop/Proyectos%20GIT/docker-clicktap/docs/NORMAS_GRAFICAS_CLICKTAP.md) y en el panel actual.

---

## 1. Objetivos del Plan

1. **Identidad visual consistente:** Aplicar el color negro de marca (`#101010`, token `--ct-sidebar`) como fondo permanente del sidebar lateral en toda la aplicación administrativa.
2. **Logotipo integrado:** Adjuntar el activo oficial [`clicktap-horizontal.png`](file:///c:/Users/vilun/OneDrive/Desktop/Proyectos%20GIT/docker-clicktap/docs/marca/clicktap-horizontal.png) en la cabecera del sidebar y en la zona de marca del header, garantizando una fusión perfecta con el fondo negro sin bordes ni recortes.
3. **Lectura completa sin elipsis (Salto de línea):** Eliminar el truncamiento automático (`truncate` / `text-overflow: ellipsis`) que impone Filament por defecto, permitiendo que las etiquetas de las vistas largas hagan un salto de línea natural (`white-space: normal; overflow-wrap: break-word`) para que todo el texto sea 100% visible sin importar el ancho compacto del sidebar (186 px).
4. **Estructura y jerarquía de vistas:** Organizar todos los recursos y páginas en grupos claros (`Inicio`, `Contenido`, `Operación`, `Finanzas`, `Sistema`) con estados inactivos, hover y activos conformes a las pautas de contraste WCAG AA.

---

## 2. Diagnóstico: Por qué se cortaban los nombres

* **Comportamiento por defecto de Filament 3:**  
  En el componente base [`item.blade.php`](file:///c:/Users/vilun/OneDrive/Desktop/Proyectos%20GIT/docker-clicktap/src/vendor/filament/filament/resources/views/components/sidebar/item.blade.php#L104), la etiqueta de navegación utiliza la clase utility:
  ```html
  <span class="fi-sidebar-item-label flex-1 truncate text-sm font-medium">
  ```
  La clase Tailwind `truncate` aplica:
  ```css
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  ```
* **Impacto en el sidebar de 186 px:**  
  Al restar el padding lateral (14-16 px por lado), el tamaño del icono (24 px) y la separación interna (12 px), el ancho útil para texto es de apenas ~115 px. Nombres como **«Stock de tarjetas»**, **«Locales de negocio»** o **«Configuración de precios»** se truncaban inevitablemente como `Stock de...`, `Locales d...` o `Configurac...`.
* **Solución requerida:**  
  Sobrescribir el estilo de `.fi-sidebar-item-label` mediante CSS de tema para anular `white-space: nowrap` y `text-overflow: ellipsis`, permitiendo saltos de línea suaves con alineación superior respecto al icono.

---

## 3. Especificación Visual y Paleta de Color (Sidebar Negro)

Siguiendo las normas gráficas oficiales de ClickTap:

| Elemento | Token / Valor | Aplicación |
|---|---|---|
| **Fondo del sidebar** | `#101010` | Superficie total de `<aside class="fi-sidebar">`, cabecera y navegación |
| **Borde lateral y divisores** | `#242820` / `#34382F` | Separador derecho del sidebar y división de la cabecera |
| **Cabecera del logo** | `#101010` | Fondo idéntico al del archivo `clicktap-horizontal.png` |
| **Títulos de grupo** | `#AAB2A0` | Mayúsculas, 11 px (0.6875rem), tracking `0.08em`, font-weight 600 |
| **Ítem inactivo (texto)** | `#D5DBCF` | Texto accesible de las vistas, 13 px, line-height 1.25 |
| **Ítem inactivo (icono)** | `#8C9681` | Icono Heroicon de 20-24 px |
| **Ítem inactivo (hover)** | Fondo `#1C2018`, Texto `#FFFFFF` | Oscurecimiento sutil con texto brillante |
| **Ítem activo (fondo)** | `#B6FF00` (Verde neón ClickTap) | Fondo de selección destacada |
| **Ítem activo (texto e icono)** | `#101010` (Negro) | Contraste de alto impacto (15.67:1 WCAG AAA) |
| **Control colapso / expandir** | `#242820`, borde `#41483A` | Botón para contraer a 72 px |

---

## 4. Listado Completo de Vistas y Saltos de Línea Planificados

El sidebar organiza las vistas del sistema en 5 grupos lógicos. A continuación se detalla cómo quebra el texto al alcanzar el límite horizontal:

### Grupo 1: Inicio
| Vista | Icono Heroicon | Ruta | Comportamiento del texto en 186 px |
|---|---|---|---|
| **Resumen** | `heroicon-o-home` | `/admin` | 1 sola línea: `Resumen` |

### Grupo 2: Contenido
| Vista | Icono Heroicon | Ruta | Comportamiento del texto en 186 px |
|---|---|---|---|
| **Clientes** | `heroicon-o-user-group` | `/admin/clientes` | 1 sola línea: `Clientes` |
| **Locales de negocio** | `heroicon-o-building-storefront` | `/admin/local-negocios` | **Salto a 2 líneas:**<br>`Locales de`<br>`negocio` |
| **Páginas** | `heroicon-o-document-duplicate` | `/admin/paginas` | 1 sola línea: `Páginas` |

### Grupo 3: Operación
| Vista | Icono Heroicon | Ruta | Comportamiento del texto en 186 px |
|---|---|---|---|
| **Stock de tarjetas** | `heroicon-o-square-3-stack-3d` | `/admin/stock-tarjetas` | **Salto a 2 líneas:**<br>`Stock de`<br>`tarjetas` |
| **Placas NFC** | `heroicon-o-qr-code` | `/admin/placas-nfc` | 1 sola línea: `Placas NFC` |
| **Solicitudes** | `heroicon-o-inbox-stack` | `/admin/solicitudes-cambio` | 1 sola línea: `Solicitudes` |

### Grupo 4: Finanzas
| Vista | Icono Heroicon | Ruta | Comportamiento del texto en 186 px |
|---|---|---|---|
| **Contabilidad** | `heroicon-o-banknotes` | `/admin/contabilidad` | 1 sola línea: `Contabilidad` |
| **Cobros** | `heroicon-o-currency-dollar` | `/admin/cobros` | 1 sola línea: `Cobros` |

### Grupo 5: Sistema
| Vista | Icono Heroicon | Ruta | Comportamiento del texto en 186 px |
|---|---|---|---|
| **Publicaciones** | `heroicon-o-cloud-arrow-up` | `/admin/publicaciones-sitio` | 1 sola línea: `Publicaciones` |
| **Configuración de precios** | `heroicon-o-cog-6-tooth` | `/admin/configuracion-precios` | **Salto a 2 líneas:**<br>`Configuración`<br>`de precios` |

---

## 5. Implementación Técnica

### A. Integración del Logotipo en `AdminPanelProvider.php`
Se vincula la imagen raster oficial mediante las APIs nativas de Filament:
```php
$panel
    ->brandName('ClickTap')
    ->brandLogo(asset('images/clicktap-horizontal.png'))
    ->brandLogoHeight('34px')
    ->sidebarWidth('186px')
    ->collapsedSidebarWidth('72px')
    ->navigationGroups(['Inicio', 'Contenido', 'Operación', 'Finanzas', 'Sistema'])
```

### B. Reglas CSS Clave en `theme.css`
Para garantizar que el fondo sea negro permanente y que los textos no se corten con elipsis:
```css
/* 1. Fondo negro del contenedor lateral y cabecera */
.fi-sidebar,
aside.fi-sidebar {
    background-color: #101010 !important;
    border-right: 1px solid #242820 !important;
}

.fi-sidebar-header,
header.fi-sidebar-header {
    background-color: #101010 !important;
    border-bottom: 1px solid #242820 !important;
}

.fi-sidebar-nav,
nav.fi-sidebar-nav {
    background-color: #101010 !important;
}

/* 2. Logo fusionado sobre fondo negro */
.fi-sidebar-header .fi-logo,
.fi-topbar-header .fi-logo,
.fi-logo,
.fi-logo img {
    background-color: #101010 !important;
    border-radius: 6px !important;
}

/* 3. Títulos de grupos en mayúsculas discretas */
.fi-sidebar-group-label {
    color: #AAB2A0 !important;
    font-size: 0.6875rem !important;
    letter-spacing: 0.08em !important;
    text-transform: uppercase !important;
    font-weight: 600 !important;
}

/* 4. Ítems de navegación con alineación vertical adecuada */
.fi-sidebar-item-button {
    color: #D5DBCF !important;
    align-items: flex-start !important;
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
    border-radius: 8px !important;
    transition: background-color 150ms ease, color 150ms ease !important;
}

.fi-sidebar-item-button:hover {
    background-color: #1C2018 !important;
    color: #FFFFFF !important;
}

/* 5. Estado activo: Verde neón #B6FF00 con texto negro #101010 */
.fi-sidebar-item.fi-active > .fi-sidebar-item-button,
.fi-sidebar-item-button.fi-active {
    background-color: #B6FF00 !important;
    color: #101010 !important;
    font-weight: 600 !important;
}

.fi-sidebar-item.fi-active > .fi-sidebar-item-button .fi-sidebar-item-icon,
.fi-sidebar-item-button.fi-active .fi-sidebar-item-icon {
    color: #101010 !important;
}

.fi-sidebar-item.fi-active > .fi-sidebar-item-button .fi-sidebar-item-label,
.fi-sidebar-item-button.fi-active .fi-sidebar-item-label {
    color: #101010 !important;
    font-weight: 600 !important;
}

/* 6. Desactivación de truncate y habilitación de salto de línea */
.fi-sidebar-item-label {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
    overflow-wrap: break-word !important;
    word-break: normal !important;
    line-height: 1.25 !important;
    min-width: 0 !important;
    flex: 1 1 0% !important;
}

.fi-sidebar-item-icon {
    flex-shrink: 0 !important;
    margin-top: 0.125rem !important;
    color: #8C9681 !important;
}
```

### C. Compilación de Assets
Los estilos se compilan en `src/public/build/admin/theme.css` mediante el script configurado:
```powershell
npm run build:admin
```

---

## 6. Comportamiento en Modo Colapsado y Móvil

1. **Modo Contraído (72 px en escritorio):**
   * El sidebar se reduce a 72 px.
   * Las etiquetas de texto se ocultan automáticamente (`x-show="$store.sidebar.isOpen"`).
   * Los iconos de 24 px permanecen centrados.
   * Se despliega un tooltip accesible flotante con el nombre completo de la vista al pasar el cursor o hacer foco.
2. **Modo Móvil (< 1024 px):**
   * El sidebar permanece oculto por defecto y se despliega como un menú lateral completo al presionar el botón de menú hamburguesa de la barra superior.
   * La cabecera superior móvil (`fi-topbar-header`) exhibe el logotipo `clicktap-horizontal.png` en el centro.
   * Al seleccionar cualquier vista en móvil, el sidebar se cierra automáticamente tras la navegación.

---

## 7. Plan de Verificación y Criterios de Aceptación

1. **Prueba de Carga del Logo:**
   * Abrir `/admin/login`: El logo `clicktap-horizontal.png` aparece centrado con sus proporciones 3:1 originales sin distorsión.
   * Abrir `/admin`: La cabecera del sidebar exhibe el logo con fondo negro continuo.
2. **Prueba de Salto de Línea:**
   * Verificar en el sidebar que **«Stock de tarjetas»** se lea íntegramente en dos líneas sin puntos suspensivos (`...`).
   * Verificar que **«Locales de negocio»** se lea íntegramente en dos líneas.
   * Verificar que el icono permanezca alineado con la primera línea de texto.
3. **Prueba de Navegación y Estados:**
   * Navegar a **Stock de tarjetas**: El ítem se resalta con fondo verde neón `#B6FF00` y tipografía negra `#101010`.
   * Navegar a **Contabilidad**: El ítem pasa a estado activo y el anterior retorna a gris suave sobre fondo negro.
4. **Pruebas Automatizadas:**
   * Ejecutar suite de pruebas de interfaz en Docker: `php artisan test --filter=AdminUiTest`. Todas las aserciones de presencia del panel y de la marca deben pasar al 100%.
