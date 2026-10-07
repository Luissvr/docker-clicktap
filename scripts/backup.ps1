param (
    [string]$Destination = ""
)

# Si no se especificó un destino, verificar si existe OneDrive o recurrir al home del usuario
if (-not $Destination) {
    if (Test-Path "$HOME\OneDrive") {
        $Destination = "$HOME\OneDrive\ClickTap_Backups"
    } else {
        $Destination = "$HOME\ClickTap_Backups"
    }
}

$date = Get-Date -Format "yyyyMMdd_HHmmss"
$targetDir = "$Destination\$date"
New-Item -ItemType Directory -Force -Path $targetDir | Out-Null

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  ClickTap - Proceso de Respaldo Local    " -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Destino: $targetDir" -ForegroundColor Gray

# 1. Respaldo de Base de Datos PostgreSQL
Write-Host "[1/2] Extrayendo volcado de PostgreSQL (clicktap_db)..." -ForegroundColor Yellow
$dumpFile = "/tmp/db_$date.dump"
docker exec clicktap_db pg_dump -U clicktap_user -F c -b -v -f $dumpFile clicktap_db
if ($LASTEXITCODE -ne 0) {
    Write-Error "Fallo al ejecutar pg_dump en el contenedor clicktap_db."
    exit 1
}

docker cp "clicktap_db:$dumpFile" "$targetDir\db_$date.dump"
docker exec clicktap_db rm $dumpFile
Write-Host "  -> Base de datos guardada en $targetDir\db_$date.dump" -ForegroundColor Green

# 2. Respaldo de Archivos Privados
Write-Host "[2/2] Respaldando archivos privados locales (storage/app/private)..." -ForegroundColor Yellow
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$privateStoragePath = Join-Path (Split-Path -Parent $scriptDir) "src\storage\app\private"

if (Test-Path $privateStoragePath) {
    $items = Get-ChildItem -Path $privateStoragePath -Exclude ".gitkeep"
    if ($items.Count -gt 0) {
        Compress-Archive -Path "$privateStoragePath\*" -DestinationPath "$targetDir\storage_$date.zip" -Force
        Write-Host "  -> Archivos privados comprimidos en $targetDir\storage_$date.zip" -ForegroundColor Green
    } else {
        Write-Host "  -> Carpeta de archivos privados vacía (sin archivos para comprimir)." -ForegroundColor DarkGray
    }
} else {
    Write-Host "  -> No se encontró ruta de archivos privados." -ForegroundColor DarkGray
}

Write-Host "==========================================" -ForegroundColor Green
Write-Host "¡Respaldo completado exitosamente!" -ForegroundColor Green
Write-Host "Ubicación: $targetDir" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
