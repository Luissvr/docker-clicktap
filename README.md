# ClickTap — Panel Administrativo Local (`docker-clicktap`)

Este repositorio contiene la infraestructura Docker, backend y base de datos relacional para el **Panel Administrativo Local de ClickTap**, construido con **Laravel 13**, **Filament v3** y **PostgreSQL 16**.

---

## 1. Arquitectura del Entorno

El entorno corre localmente mediante Docker Compose con los siguientes servicios:

* **`clicktap_web` (Nginx Alpine):** Servidor web expuesto únicamente en `http://127.0.0.1:8080`.
* **`clicktap_app` (PHP 8.3-FPM Alpine):** Contenedor con Laravel 13, Filament v3, Composer y extensiones (`pdo_pgsql`, `gd`, `intl`, `zip`, `exif`, `bcmath`, `opcache`).
* **`clicktap_db` (PostgreSQL 16 Alpine):** Base de datos relacional con volumen persistente (`clicktap_postgres_data`), expuesta localmente en `127.0.0.1:5432`.
* **`clicktap_vendor_data`:** Dependencias PHP dentro de Docker para evitar la latencia del montaje de Windows. Se completa automáticamente en el primer inicio.
* **`clicktap_runtime_data`:** Vistas y cachés temporales de Laravel dentro de Docker; no contiene datos de negocio.

---

## 2. Requisitos Previos

* [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado y en ejecución en Windows/Mac/Linux.
* Git.

El repositorio puede permanecer en una carpeta de Windows. Composer y la caché de Laravel se guardan en volúmenes de Docker para evitar leer miles de archivos por el montaje de Windows en cada petición.

---

## 3. Puesta en Marcha Rápida

1. **Iniciar los contenedores:**
   ```powershell
   cd "C:\ruta\docker-clicktap"
   docker compose up -d
   ```
   El primer inicio puede tardar mientras Docker prepara las dependencias; los siguientes reutilizan los volúmenes. Ejecuta `docker compose up -d` también después de traer cambios en `docker-compose.yml`: Compose recreará los contenedores necesarios. Abrir Docker Desktop por sí solo no aplica la configuración nueva.

2. **Acceder al panel de administración:**
   * URL: [http://localhost:8080/admin](http://localhost:8080/admin)
   * Usar las credenciales administrativas configuradas localmente. Las contraseñas no se documentan en el repositorio.

3. **Detener los contenedores:**
   ```powershell
   docker compose down
   ```

---

## 4. Comandos de Mantenimiento

* **Ejecutar migraciones:**
  ```powershell
  docker compose exec app php artisan migrate
  ```
* **Ejecutar seeders:**
  ```powershell
  docker compose exec app php artisan db:seed
  ```
* **Actualizar dependencias PHP después de cambiar `composer.lock`:**
  ```powershell
  docker compose exec app composer install
  ```
* **Abrir consola interactiva de Laravel (Tinker):**
  ```powershell
  docker compose exec app php artisan tinker
  ```
* **Ver registros en tiempo real:**
  ```powershell
  docker compose logs -f
  ```

---

## 5. Respaldo y Restauración (`Backup & Restore`)

Dado que la base de datos corre de manera local, se incluyen scripts automatizados para salvaguardar la información:

### Crear un Respaldo
El script genera un volcado binario consistente de PostgreSQL (`.dump`) y comprime la carpeta de archivos privados (`src/storage/app/private`):

* **Por defecto (detecta OneDrive o crea carpeta en el usuario):**
  ```powershell
  .\scripts\backup.ps1
  ```
* **Especificando una ruta personalizada (ej. unidad USB o externa):**
  ```powershell
  .\scripts\backup.ps1 -Destination "D:\MisRespaldos\ClickTap"
  ```

### Restaurar un Respaldo
Restaura el esquema completo y los datos en PostgreSQL:
```powershell
.\scripts\restore.ps1 -DumpFile "C:\Ruta\Al\Archivo\db_YYYYMMDD_HHMMSS.dump"
```

*(En entornos Linux/WSL/POSIX se dispone de los equivalentes `scripts/backup.sh` y `scripts/restore.sh`).*

---

## 6. Uso del panel

El flujo inicial recomendado es:

1. Crear una **Página** con el nombre público del local.
2. El sistema crea automáticamente el **Local**.
3. Asociar un **Cliente** solo si ya corresponde; es opcional.
4. Completar descripción, contacto, logo, colores y enlaces.
5. Guardar y abrir **Vista previa** desde la lista de páginas.

La página guarda directamente el contenido vigente. Los enlaces pertenecen a la página, pueden ordenarse o desactivarse y pueden guardarse sin destino mientras se completan. Existe un tipo de enlace para **Datos de transferencia** y cualquier enlace puede configurarse para copiar un texto al pulsarlo. Al cargar un logo, el panel propone una paleta de colores editable. No se utilizan borradores, aprobaciones ni historial de versiones en el MVP simplificado.

### Preparar una publicación pública

Con Docker, Node.js y el repositorio Astro disponible localmente, se puede preparar el contenido y comprobar el build con:

```powershell
.\scripts\prepare-publish.ps1 -AstroPath "C:\dev\proyecto"
```

El script exporta las páginas activas, copia los logos usados y ejecuta las pruebas y el build de Astro. Deja los cambios listos para revisión, pero no hace commit, push ni elimina recursos existentes.

### Publicar desde el panel

La publicación del sitio se inicia manualmente desde el botón **Publicar** de una página activa. Guardar una página no la publica. Antes de usarlo, configura en `src/.env`:

```dotenv
GITHUB_API_TOKEN=tu_token_de_contenidos
GITHUB_REPOSITORY=bmartinezr1/linktree-a-lo-weillo
GITHUB_PUBLICATION_BRANCH=main
```

El token debe tener permisos de lectura y escritura de contenidos en el repositorio Astro. El panel registra si el commit fue enviado o falló; la propagación posterior de Cloudflare puede tardar.

## 7. Modelo de datos y seguridad

* **Relaciones activas:** Cliente opcional → Local → Página → Enlace de página.
* **UUIDs:** Las entidades utilizan identificadores UUID.
* **Sin registro público:** solo usuarios activos creados previamente pueden acceder a Filament.
* **Rutas estables:** el slug no cambia después de crear la página y no puede colisionar con rutas técnicas o alias históricos.
* **Logos:** se guardan en el disco público de Laravel mediante `public/storage`. Al publicar se copian únicamente los logos usados a `public/generated/logos` del repositorio Astro; no se eliminan recursos existentes.


## 8. Pruebas

La suite normal usa SQLite en memoria y no puede conectarse a la base personal:

```powershell
docker compose exec app php artisan test --compact
```

Las restricciones específicas de PostgreSQL se comprueban en `clicktap_test`:

```powershell
docker compose exec app vendor/bin/phpunit --configuration phpunit.postgres.xml
```

El entorno de pruebas fija internamente el nombre `clicktap_test` y aborta antes de ejecutar migraciones si una conexión PostgreSQL intenta usar otro nombre. La base de pruebas se crea una vez con:

```powershell
docker compose exec db sh -lc 'createdb --username="$POSTGRES_USER" clicktap_test'
```

## 9. Solución de problemas

* Si el formulario de acceso muestra que `POST admin/login` no está permitido, recargar con `Ctrl+F5`. Ese mensaje aparece cuando el formulario quedó abierto durante un reinicio y el JavaScript de Livewire no cargó.
* Comprobar los servicios con `docker compose ps`. Los tres deben aparecer como `Up`.
* Comprobar Livewire abriendo `http://127.0.0.1:8080/livewire/livewire.js`; debe responder JavaScript.
