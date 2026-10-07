#!/usr/bin/env bash
set -e

DESTINATION="${1:-$HOME/ClickTap_Backups}"
DATE=$(date +"%Y%m%d_%H%M%S")
TARGET_DIR="$DESTINATION/$DATE"

mkdir -p "$TARGET_DIR"

echo "=========================================="
echo "  ClickTap - Proceso de Respaldo Local    "
echo "=========================================="
echo "Destino: $TARGET_DIR"

echo "[1/2] Extrayendo volcado de PostgreSQL (clicktap_db)..."
docker exec clicktap_db pg_dump -U clicktap_user -F c -b -v -f "/tmp/db_$DATE.dump" clicktap_db
docker cp "clicktap_db:/tmp/db_$DATE.dump" "$TARGET_DIR/db_$DATE.dump"
docker exec clicktap_db rm "/tmp/db_$DATE.dump"
echo "  -> Base de datos guardada en $TARGET_DIR/db_$DATE.dump"

echo "[2/2] Respaldando archivos privados locales..."
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PRIVATE_STORAGE="$SCRIPT_DIR/../src/storage/app/private"

if [ -d "$PRIVATE_STORAGE" ] && [ "$(ls -A "$PRIVATE_STORAGE" 2>/dev/null)" ]; then
    tar -czf "$TARGET_DIR/storage_$DATE.tar.gz" -C "$PRIVATE_STORAGE" .
    echo "  -> Archivos privados comprimidos en $TARGET_DIR/storage_$DATE.tar.gz"
else
    echo "  -> Carpeta de archivos privados vacía o no encontrada."
fi

echo "=========================================="
echo "¡Respaldo completado exitosamente!"
echo "Ubicación: $TARGET_DIR"
echo "=========================================="
