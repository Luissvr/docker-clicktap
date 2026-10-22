---
name: clicktap-react-design
description: >-
  Design system guidelines and UI component migration guide adapting React 19 + Tailwind CSS v4 + Radix UI from laravel-p6-2026 into the ClickTap control panel and Astro preview pages.
---

# ClickTap React & Tailwind v4 Design System Skill

Este skill define la arquitectura visual, componentes React 19, tokens de diseño y directivas de adaptación extraídas del proyecto `laravel-p6-2026-s1-main` para su integración en el panel de control de **ClickTap** (`docker-clicktap`) y su contraparte de visualización en **Astro**.

---

## 1. Mapeo de Tokens y Paleta de Marca

En `laravel-p6-2026`, la paleta se basaba en verdes AVA (`--color-ava-verde-*`) y oscuros (`#15171c`). En ClickTap, mapeamos directamente estos roles a la identidad corporativa oficial (`docs/NORMAS_GRAFICAS_CLICKTAP.md`):

| Rol Semántico | Token laravel-p6 | Token ClickTap | Valor Hex | Uso en ClickTap |
|---|---|---|---|---|
| **Acento Primario** | `--color-ava-verde-5` | `--ct-neon` / `--color-primary` | `#B6FF00` | Botones de acción principal, badges activos, focus rings |
| **Acento Hover** | `--color-ava-verde-6` | `--ct-neon-hover` | `#A0F700` | Estado hover de botones primarios |
| **Sidebar Background** | `--color-ava-negro-1` | `--ct-sidebar` / `--color-sidebar` | `#101010` | Barra lateral fija |
| **Sidebar Active** | `--color-ava-verde-5/10` | `--ct-sidebar-active` | `rgba(182, 255, 0, 0.1)` | Item activo en navegación con borde verde |
| **Área de Trabajo** | `bg-slate-50` | `--ct-bg` | `#F6F7F5` | Fondo general de la aplicación |
| **Tarjetas / Paper** | `bg-white` | `--ct-paper` | `#FFFFFF` | Contenedores modulares, modales, formularios |
| **Bordes y Divisores** | `--color-ava-verde-3` | `--ct-line` / `--color-border` | `#DCE0D5` | Separadores y bordes de inputs |
| **Texto Principal** | `--color-foreground` | `--ct-ink` | `#101010` | Tipografía principal de lectura |
| **Texto Secundario** | `--color-ava-gris-1` | `--ct-muted` | `#596052` | Etiquetas, placeholders, fechas |

### Regla Crítica de Contraste (Tokens `-fg` >= 4.5:1)
El verde neón `#B6FF00` sobre blanco tiene un contraste de ~1.2:1 (inaccesible para lectura de texto). Siguiendo el patrón de `laravel-p6-2026`:
- **NUNCA** usar `#B6FF00` como color de texto sobre fondo claro (`#FFFFFF` o `#F6F7F5`).
- Usar el token semántico `--color-status-ok-fg: #3f6212` (verde bosque profundo, ~6.9:1) para textos de éxito sobre fondos claros.
- Reservar `#B6FF00` únicamente para fondos de badges con texto negro (`text-black`), botones principales con texto oscuro (`text-[#101010] font-semibold`), o acentos sobre el sidebar oscuro (`#101010`).

---

## 2. Configuración en Tailwind CSS v4 (`resources/css/app.css`)

```css
@import 'tailwindcss';
@plugin 'tailwindcss-animate';

@theme {
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
    --font-mono: 'JetBrains Mono', monospace;

    /* ClickTap Brand Colors */
    --color-ct-neon: #B6FF00;
    --color-ct-dark: #101010;
    --color-ct-bg: #F6F7F5;
    --color-ct-paper: #FFFFFF;
    --color-ct-line: #DCE0D5;
    --color-ct-muted: #596052;

    /* Semantic Theme Tokens */
    --color-background: var(--ct-bg);
    --color-foreground: var(--ct-ink);
    --color-card: var(--ct-paper);
    --color-card-foreground: var(--ct-ink);
    --color-border: var(--ct-line);

    --color-primary: #B6FF00;
    --color-primary-foreground: #101010;

    --color-sidebar: #101010;
    --color-sidebar-foreground: #D5DBCF;
    --color-sidebar-border: #34382F;

    /* Semantic Status Indicators */
    --color-status-ok: #B6FF00;
    --color-status-ok-fg: #3f6212;
    --color-status-warn: #FFCB00;
    --color-status-warn-fg: #854d0e;
    --color-status-crit: #F80000;
    --color-status-crit-fg: #b70000;
}
```

---

## 3. Catálogo de Componentes Reutilizables (Extraídos de p6)

1. **Sidebar (`Sidebar.tsx`)**:
   - Soporta colapso a riel de iconos (72px / `w-[4.75rem]`) y expansión completa (186px / `w-64`).
   - Persistencia de estado en `localStorage` con shortcut `Ctrl/Cmd + B`.
   - Soporta drawer en móvil (`<lg`) con backdrop y cierre por tecla `Escape`.
   - Footer con perfil de usuario (iniciales en avatar con `--ct-neon`, nombre, cargo y logout).
2. **Layout Shell (`AppSidebarLayout.tsx`)**:
   - `flex h-dvh overflow-hidden bg-[#F6F7F5]`.
   - Header superior con selector de Local/Negocio activo (`ProjectSelector.tsx` adaptado a `LocalSelector.tsx`).
   - Contenedor principal con `min-h-0 min-w-0 overflow-y-auto` que garantiza que el scroll no arrastra el sidebar.
3. **Primitives de Radix UI & Shadcn**:
   - `dialog.tsx`: Modales de confirmación, altas y edición de enlaces.
   - `dropdown-menu.tsx`: Menús contextuales en tarjetas y acciones de local.
   - `tooltip.tsx`: Información contextual en el modo colapsado del sidebar.
   - `photo-lightbox.tsx` / `evidencia-img.tsx`: Visor de logos, placas NFC y vistas previas.
4. **Métricas y Gráficos (Recharts)**:
   - `recharts` integrado para visualización de visitas a enlaces, clics en tarjetas NFC y escaneos de QR.

---

## 4. Adaptación para Vistas Previas en Astro

Para reutilizar estos componentes en el sitio de clientes o visor dinámico de Astro:
1. Instalar `@astrojs/react` en el proyecto Astro.
2. Usar directivas de hidratación selectiva:
   - Componentes interactivos (ej. carrusel de fotos, acordeón de enlaces): `<LinkTreeAccordion client:load />`.
   - Modales o tooltips de contacto: `<ContactDialog client:visible />`.
   - Componentes visuales estáticos (tarjetas de enlace, headers): `<ProfileCard />` (0 KB JavaScript en cliente).
