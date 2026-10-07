param (
    [Parameter(Mandatory=$true)]
    [string]$DumpFile
)

if (-not (Test-Path $DumpFile)) {
    Write-Error "El archivo de respaldo especificado no existe: $DumpFile"
    exit 1
}

Write-Host "==========================================" -ForegroundColor Yellow
Write-Host "  ClickTap - Restauración de Base de Datos" -ForegroundColor Yellow
Write-Host "==========================================" -ForegroundColor Yellow
Write-Host "Restaurando desde: $DumpFile" -ForegroundColor Gray

docker cp $DumpFile clicktap_db:/tmp/restore.dump
if ($LASTEXITCODE -ne 0) {
    Write-Error "Error al transferir el archivo al contenedor clicktap_db."
    exit 1
}

# pg_restore con limpieza de tablas previas (-c)
docker exec clicktap_db pg_restore -U clicktap_user -d clicktap_db -c -v "/tmp/restore.dump"
$restoreExit = $LASTEXITCODE

docker exec clicktap_db rm "/tmp/restore.dump"

if ($restoreExit -eq 0 -or $restoreExit -eq 1) {
    Write-Host "==========================================" -ForegroundColor Green
    Write-Host "¡Restauración finalizada exitosamente!" -ForegroundColor Green
    Write-Host "==========================================" -ForegroundColor Green
} else {
    Write-Error "Ocurrió un error durante pg_restore. Código: $restoreExit"
    exit 1
}
