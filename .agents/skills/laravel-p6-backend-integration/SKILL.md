---
name: laravel-p6-backend-integration
description: >-
  Procedures and architecture guidelines for integrating Inertia.js 2.0 + React with Laravel 13, Docker Compose, PostgreSQL 16, and Supabase sync from laravel-p6-2026 into docker-clicktap.
---

# ClickTap Laravel Backend & Inertia Integration Skill

Este skill detalla el procedimiento técnico para migrar la arquitectura backend de `laravel-p6-2026-s1-main` (basada en Inertia.js + React 19 + TypeScript + Vite) al entorno contenerizado de **ClickTap** (`docker-clicktap`).

---

## 1. Configuración de Inertia.js en Laravel 13

### 1.1 Dependencias de PHP
En el contenedor `clicktap_app` (o vía Composer en `src/composer.json`):
```json
"require": {
    "php": "^8.3",
    "inertiajs/inertia-laravel": "^2.0",
    "laravel/framework": "^13.17",
    "league/flysystem-aws-s3-v3": "^3.0"
}
```

### 1.2 Middleware `HandleInertiaRequests`
Registrar el middleware en `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\HandleInertiaRequests::class,
    ]);
})
```

Contenido clave de `app/Http/Middleware/HandleInertiaRequests.php`:
```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
            ],
            // Contexto activo del local (similar a proyIdActual de p6)
            'localActual' => $request->session()->get('local_activo_id'),
            'localesDisponibles' => fn () => $request->user()
                ? \App\Models\LocalNegocio::select('id', 'nombre')->get()
                : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
```

### 1.3 Vista Base (`resources/views/app.blade.php`)
```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'ClickTap') }}</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-[#F6F7F5] text-[#101010]">
        @inertia
    </body>
</html>
```

---

## 2. Docker & Pipeline de Vite

En `docker-clicktap`, Nginx expone el puerto `8080` y la aplicación corre en PHP-FPM (`clicktap_app`). Para soportar desarrollo con Hot Module Replacement (HMR) de Vite y React 19:

### 2.1 Configuración de `docker-compose.yml`
Exponer el puerto de Vite (5173 o 3000) en el servicio `app` o correr Vite localmente en el host:
```yaml
services:
  web:
    ports:
      - "127.0.0.1:8080:80"
  app:
    ports:
      - "127.0.0.1:5173:5173" # Puerto Vite HMR
```

### 2.2 Configuración de `vite.config.ts`
```typescript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
            clientPort: 5173,
        },
    },
});
```

---

## 3. Mapeo del Modelo de Dominio ClickTap

Integración de las entidades de ClickTap con los controladores Inertia:

1. **Dashboard & Locales (`DashboardController.php`)**:
   - Muestra métricas agregadas: total de locales, páginas publicadas, tarjetas NFC vinculadas y clics recibidos.
   - Retorna: `Inertia::render('Dashboard', [ 'locales' => $locales, 'stats' => $stats ])`.
2. **Editor de Página Pública (`PaginaController.php`)**:
   - Gestiona `paginas` y `enlaces_pagina`.
   - Soporta validación de slugs únicos (`PageRoute.php`).
   - Retorna la vista `Inertia::render('Pagina/Editor', [ 'pagina' => $pagina, 'enlaces' => $pagina->enlaces ])`.
3. **Flujo de Publicación y Previews (`PreviewController.php` & Supabase Sync)**:
   - Al pulsar "Guardar Borrador", persiste en PostgreSQL local (`clicktap_db`).
   - Al pulsar "Generar Vista Previa", invoca el exportador hacia Supabase (`app/Console/Commands/SyncLocalCatalog.php` / `PublicPagePublisher.php`).
   - Devuelve la URL estable del visor Astro (`https://preview.clicktap.app/v/{token}`).

---

## 4. Estrategia de Convivencia o Reemplazo de Filament

`docker-clicktap` incluye Filament v3 en `/admin`. Para una transición sin riesgos:
- **Estrategia A (Panel Paralelo):** Mantener Filament en `/admin` para administración interna y emergencias de base de datos; montar la nueva UI en React/Inertia en `/` y `/app` para la operación diaria y comercial.
- **Estrategia B (Reemplazo Total):** Migrar las pantallas de Filament (`ClienteResource`, `LocalNegocioResource`, `PaginaResource`, `StockTarjetas`, `Cobros`) a páginas Inertia (`resources/js/pages/...`), logrando 100% de coherencia visual y eliminando el dual-build de Tailwind v3/v4.
