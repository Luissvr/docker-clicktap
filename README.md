# ClickTap — Panel Administrativo Local (`docker-clicktap`)

Este repositorio contiene la infraestructura Docker, backend y base de datos relacional para el **Panel Administrativo Local de ClickTap**, construido con **Laravel 13**, **Filament v3** y **PostgreSQL 16**.

---

## 1. Arquitectura del Entorno

El entorno corre localmente mediante Docker Compose con los siguientes servicios:

* **`clicktap_web` (Nginx Alpine):** Servidor web expuesto únicamente en `http://127.0.0.1:8080`.
* **`clicktap_app` (PHP 8.3-FPM Alpine):** Contenedor con Laravel 13, Filament v3, Composer y extensiones (`pdo_pgsql`, `gd`, `intl`, `zip`, `exif`, `bcmath`, `opcache`).
* **`clicktap_db` (PostgreSQL 16 Alpine):** Base de datos relacional con volumen persistente (`clicktap_postgres_data`), expuesta localmente en `127.0.0.1:5432`.

---

## 2. Requisitos Previos

* [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado y en ejecución en Windows/Mac/Linux.
* Git.

---

## 3. Puesta en Marcha Rápida

1. **Iniciar los contenedores:**
   ```powershell
   docker compose up -d
   ```

2. **Acceder al panel de administración:**
   * URL: [http://localhost:8080/admin](http://localhost:8080/admin)
   * **Usuario inicial:** `admin@clicktap.app`
   * **Contraseña inicial:** `ClickTap2026!`

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

## 6. Modelo de Datos y Seguridad

* **UUIDs:** Todas las 16 entidades de negocio y auditoría emplean identificadores UUID para garantizar unicidad y evitar colisiones de IDs secuenciales.
* **Sin Registro Público:** La auto-inscripción está deshabilitada en Filament; solo los usuarios administradores autorizados en la base de datos pueden acceder.
* **Archivos Protegidos:** Los logos originales y archivos no publicados se gestionan en disco privado local (`src/storage/app/private`) y nunca se exponen directamente al servidor web público.
