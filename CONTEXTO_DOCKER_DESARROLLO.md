# Contexto de Docker y desarrollo local de ClickTap

Documento de traspaso para otro agente. Basado en la lectura del repositorio el 6 de octubre de 2026. Describe la configuración del código; no acredita que los contenedores estén ejecutándose ni que la instalación haya sido probada.

## Objetivo del usuario

Desarrollar las funciones del administrador de ClickTap y probar su efecto en la aplicación pública ejecutada localmente con `npm run dev`, sin realizar pruebas directamente en producción en Cloudflare.

Este repositorio contiene la infraestructura y el administrador Laravel/Filament. El código de la aplicación pública y su configuración de Cloudflare no se han inspeccionado. Antes de implementar la comunicación, localizar ese proyecto y determinar cómo obtiene su contenido.

## Arquitectura actual

```text
Navegador del equipo
  http://localhost:8080/admin
       |
       v
  web / clicktap_web       Nginx, puerto interno 80
       |
       | FastCGI a app:9000
       v
  app / clicktap_app       PHP-FPM + Laravel + Filament
       |
       | PostgreSQL a clicktap_db:5432
       v
  db / clicktap_db         PostgreSQL 16
       |
       v
  volumen clicktap_postgres_data
```

La definición está en `docker-compose.yml`.

| Servicio Compose | Contenedor | Función | Acceso desde el equipo |
| --- | --- | --- | --- |
| `web` | `clicktap_web` | Nginx sirve `src/public` y pasa PHP a FPM | `http://localhost:8080` |
| `app` | `clicktap_app` | Ejecuta Laravel y comandos PHP/Composer | Mediante `docker compose exec app ...` |
| `db` | `clicktap_db` | PostgreSQL 16 Alpine | `127.0.0.1:5432` |

- Los puertos publicados de Nginx y PostgreSQL están vinculados a `127.0.0.1`.
- Los tres servicios comparten la red bridge `clicktap_network` de Compose.
- `./src` se monta en `/var/www/html` tanto en `app` como en `web`: los cambios de código del equipo llegan al contenedor sin reconstruir la imagen.
- La imagen PHP se construye desde `.docker/php/Dockerfile`, con PHP 8.3-FPM Alpine, Composer 2 y extensiones para PostgreSQL, imágenes, ZIP e internacionalización. No instala Node/npm ni las dependencias de la aplicación.
- `.docker/php/local.ini` configura zona horaria `America/Santiago`, memoria de 512 MB y límites de subida de 40 MB. Nginx también limita el cuerpo a 40 MB.
- Hay `depends_on`, pero no hay healthchecks que garanticen que PostgreSQL ya acepta conexiones al iniciar Laravel.
- No hay servicio de Vite, worker de colas, scheduler ni despliegue a Cloudflare en Compose.

## Variables de entorno y persistencia

Hay dos archivos con responsabilidades distintas:

1. `.env` en la raíz: Compose lo utiliza para sustituir `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` en su configuración.
2. `src/.env`: Laravel lee la configuración de aplicación, como `APP_KEY`, `APP_URL`, sesión, caché y colas.

Compose inyecta las variables `DB_*` en `app`; esas variables de proceso tienen prioridad sobre las del archivo de Laravel. Dentro del contenedor, la conexión debe usar el host configurado `clicktap_db`, no `localhost`. Desde un cliente PostgreSQL en Windows se usa `127.0.0.1:5432`.

Configuración local prevista para `src/.env`:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080
```

`APP_URL` es la URL del propio administrador. No configura por sí sola una conexión con la aplicación pública. El archivo `src/.env.example` todavía contiene valores genéricos de Laravel, como SQLite y el puerto 8000; no debe tomarse como una descripción completa del entorno Docker.

PostgreSQL persiste en el volumen nombrado `clicktap_postgres_data`. `docker compose down` conserva ese volumen; `docker compose down -v` lo elimina. Cambiar las credenciales del `.env` no cambia automáticamente los usuarios de una base ya inicializada.

Los archivos de Laravel en `src/storage` persisten mediante el montaje del código. El disco `local` está configurado en `src/storage/app/private`; el disco público usa `src/storage/app/public`. No se deben exponer archivos privados para resolver la vista previa.

Hay scripts de respaldo y restauración en `scripts/`, tanto PowerShell como shell. Revisar sus parámetros antes de usarlos; restaurar modifica los datos locales.

## Arranque y trabajo diario

Requisitos: Docker Desktop activo. Para Vite ejecutado desde Windows, Node/npm compatibles con las dependencias declaradas en `src/package.json`.

Ejecutar desde la raíz del repositorio:

```powershell
docker compose up -d --build
```

En una instalación nueva, preparar los archivos `.env` a partir de los ejemplos si no existen y ajustar la configuración anterior. No sobrescribir archivos `.env` existentes. Instalar las dependencias PHP si falta `src/vendor`:

```powershell
docker compose exec app composer install
```

Generar la clave únicamente si `APP_KEY` está vacía:

```powershell
docker compose exec app php artisan key:generate
```

Con PostgreSQL disponible y la configuración local revisada:

```powershell
docker compose exec app php artisan migrate --seed
```

El seeder crea un administrador inicial; consultar `src/database/seeders/DatabaseSeeder.php` para sus valores. Abrir `http://localhost:8080/admin`.

Comandos de operación desde la raíz:

```powershell
docker compose ps
docker compose logs --tail=100 app web db
docker compose exec app php artisan migrate
docker compose exec app php artisan config:clear
docker compose down
```

Usar `config:clear` cuando haya configuración Laravel cacheada después de cambiar `src/.env`. Si cambian las variables inyectadas por Compose, recrear el servicio con `docker compose up -d app`. Los cambios del Dockerfile requieren reconstruir la imagen.

## Los dos posibles significados de npm run dev

### Vite de este administrador

`src/package.json` define `dev` como `vite`. Desde otra terminal:

```powershell
Set-Location .\src
npm install
npm run dev
```

Vite procesa los puntos de entrada `resources/css/app.css` y `resources/js/app.js` configurados en `src/vite.config.js`. Docker sigue ejecutando PHP, y la URL del administrador sigue siendo `http://localhost:8080/admin`. La URL que imprima Vite sirve recursos de desarrollo; no sustituye al servidor Laravel.

Filament tiene sus propios recursos publicados en `src/public/css/filament` y `src/public/js/filament`. El proveedor del panel actual no registra un tema Vite personalizado. No asumir que modificar `resources/css/app.css` cambia automáticamente el estilo de Filament ni que todo cambio de PHP provoca recarga automática del navegador.

### Servidor de desarrollo de la aplicación pública

Si el usuario habla de `npm run dev` en otro repositorio, es un proceso distinto. Su framework, puerto, rutas y origen de datos deben revisarse allí. Ejecutar ambos servidores no crea una conexión entre ellos.

## Estado real de las funciones del administrador

- `src/app/Providers/Filament/AdminPanelProvider.php` registra `/admin`, login, dashboard y descubrimiento de recursos.
- El único modelo de aplicación encontrado es `User`; no hay recursos de negocio Filament implementados en el código revisado.
- Hay migraciones para clientes, locales, páginas, versiones, enlaces, archivos, placas NFC, solicitudes, cobros, publicaciones y auditoría, entre otras. Tener las tablas no implica que sus pantallas o procesos estén implementados.
- `src/routes/web.php` define la página de bienvenida. `src/bootstrap/app.php` registra las rutas web, consola y `/up`.
- No existe `src/routes/api.php` ni se registra un archivo de rutas API. La regla para renderizar errores JSON en `api/*` no crea endpoints.
- No se encontró un proceso implementado que exporte contenido o publique en Cloudflare.

## Conexión pendiente con la aplicación pública

La comunicación debe decidirse después de leer el otro proyecto. Hay dos opciones según su arquitectura:

1. **Contenido leído por HTTP:** implementar endpoints locales en Laravel y configurar la aplicación pública para consultarlos. Una URL como `http://localhost:8080/api` sería una configuración futura; hoy no existe esa API. Definir autenticación para operaciones protegidas y, si las peticiones salen del navegador entre orígenes distintos, un proxy de desarrollo o CORS limitado al origen local correspondiente.
2. **Sitio generado con archivos:** implementar una exportación local de JSON y archivos que la aplicación pública consuma en desarrollo. Esto puede conservar el flujo de un sitio estático sin requerir una API en tiempo de ejecución.

La migración `2026_10_06_000013_create_publicaciones_sitio_table.php` contiene campos de manifiesto, hash, commit de exportación, proyecto y rama de destino. Sus valores predeterminados incluyen el proyecto `linktree-a-lo-weillo` y la rama `main`. Es un indicio de un diseño de publicación mediante exportaciones, no evidencia de que ese proceso ya exista. Revisar esta intención antes de añadir una API por defecto.

El flujo de desarrollo que se implemente debe permitir editar en el admin local, guardar en PostgreSQL local y observar el resultado en la app pública local. La vista previa no debe disparar publicaciones ni escrituras en Cloudflare o en la rama de producción.

Si Laravel dentro de Docker necesita llamar a un servidor ejecutado en Windows, considerar `host.docker.internal` y comprobar que el servidor acepta esa conexión. `localhost` dentro de `app` apunta al propio contenedor. Si quien llama es el navegador de Windows, se usan las URLs locales del equipo.

## Referencias y límites del análisis

- Infraestructura: `docker-compose.yml`, `.docker/php/Dockerfile`, `.docker/php/local.ini`, `.docker/nginx/default.conf`.
- Dependencias y recursos: `src/composer.json`, `src/composer.lock`, `src/package.json`, `src/vite.config.js`.
- Aplicación: `src/bootstrap/app.php`, `src/routes/web.php`, `src/app/Providers/Filament/AdminPanelProvider.php`.
- Datos: `src/database/migrations/`, `src/database/seeders/DatabaseSeeder.php`, `src/config/filesystems.php`.
- Instrucciones para modificar Laravel: leer `src/AGENTS.md` antes de intervenir en la aplicación.

El proyecto utiliza Laravel 13: `src/composer.json` declara `laravel/framework: ^13.17`. El README raíz refleja esta versión. Utilizar los manifiestos y el lockfile para determinar las versiones exactas.

Este traspaso se elaboró mediante lectura del código. No se iniciaron contenedores, instalaron dependencias, ejecutaron migraciones ni probaron conexiones. Tampoco se inspeccionaron los valores privados de los archivos `.env` ni la configuración real de producción.
