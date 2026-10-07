#!/usr/bin/env bash
set -e

DUMP_FILE="$1"

if [ -z "$DUMP_FILE" ] || [ ! -f "$DUMP_FILE" ]; then
    echo "Uso: ./restore.sh /ruta/al/archivo.dump"
    exit 1
fi

echo "=========================================="
echo "  ClickTap - Restauración de Base de Datos"
echo "=========================================="
echo "Restaurando desde: $DUMP_FILE"

docker cp "$DUMP_FILE" clicktap_db:/tmp/restore.dump

docker exec clicktap_db pg_restore -U clicktap_user -d clicktap_db -c -v "/tmp/restore.dump" || true

docker exec clicktap_db rm "/tmp/restore.dump"

echo "=========================================="
echo "¡Restauración finalizada!"
echo "=========================================="
