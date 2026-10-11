[CmdletBinding()]
param (
    [string]$AstroPath = "$(Join-Path (Split-Path -Parent $PSScriptRoot) '..\proyecto')"
)

$ErrorActionPreference = 'Stop'
$adminPath = Split-Path -Parent $PSScriptRoot
$exportPath = Join-Path $adminPath 'src\storage\app\public\clicktap-pages.json'
$astroDataPath = Join-Path $AstroPath 'src\data\generated\pages.json'
$astroLogoPath = Join-Path $AstroPath 'public\generated\logos'
$publicStoragePath = Join-Path $adminPath 'src\storage\app\public'

function Invoke-Native {
    param (
        [string]$File,
        [string[]]$Arguments,
        [string]$WorkingDirectory
    )

    Push-Location $WorkingDirectory
    try {
        & $File @Arguments
        $exitCode = $LASTEXITCODE
    } finally {
        Pop-Location
    }

    if ($exitCode -ne 0) {
        throw "El comando '$File' terminó con código $exitCode."
    }
}

if (-not (Test-Path -LiteralPath $AstroPath -PathType Container)) {
    throw "No se encontró el repositorio Astro: $AstroPath"
}

$astroStatus = (& git -C $AstroPath status --porcelain --untracked-files=all)
if ($astroStatus) {
    throw 'El repositorio Astro tiene cambios sin guardar. Haz commit o guárdalos antes de preparar una publicación.'
}

Write-Host '[1/4] Exportando páginas públicas desde Laravel...' -ForegroundColor Yellow
Invoke-Native 'docker' @(
    'compose', 'exec', '-T', '-u', 'www-data', 'app', 'php', 'artisan',
    'clicktap:export-public', '--output=/var/www/html/storage/app/public/clicktap-pages.json'
) $adminPath

if (-not (Test-Path -LiteralPath $exportPath -PathType Leaf)) {
    throw "Laravel no generó el contrato: $exportPath"
}

$payload = Get-Content -LiteralPath $exportPath -Raw | ConvertFrom-Json
if ($payload.version -ne 1 -or $null -eq $payload.pages) {
    throw 'El contrato exportado no tiene el formato público esperado.'
}

New-Item -ItemType Directory -Force -Path (Split-Path -Parent $astroDataPath), $astroLogoPath | Out-Null

foreach ($page in @($payload.pages)) {
    if (-not ($page.slug -match '^[a-zA-Z0-9][a-zA-Z0-9_-]*$')) {
        throw "Slug no válido para exportar recursos: $($page.slug)"
    }

    if ($page.logo -like '/storage/*') {
        $relativeLogo = $page.logo.Substring('/storage/'.Length).Replace('/', '\')
        $sourceLogo = Join-Path $publicStoragePath $relativeLogo
        if (-not (Test-Path -LiteralPath $sourceLogo -PathType Leaf)) {
            throw "No se encontró el logo público de $($page.slug): $sourceLogo"
        }

        $extension = [IO.Path]::GetExtension($sourceLogo).ToLowerInvariant()
        if ($extension -notin @('.png', '.jpg', '.jpeg', '.webp', '.gif', '.svg')) {
            throw "Formato de logo no permitido para $($page.slug): $extension"
        }

        $logoName = "$($page.slug)$extension"
        Copy-Item -LiteralPath $sourceLogo -Destination (Join-Path $astroLogoPath $logoName) -Force
        $publicLogo = "/generated/logos/$logoName"
        $page.logo = $publicLogo
        if ($page.favicon -like '/storage/*') {
            $page.favicon = $publicLogo
        }
    }
}

$json = $payload | ConvertTo-Json -Depth 20
[IO.File]::WriteAllText($astroDataPath, $json, [Text.UTF8Encoding]::new($false))

Write-Host '[2/4] Ejecutando pruebas y build de Astro...' -ForegroundColor Yellow
Invoke-Native 'npm' @('run', 'build') $AstroPath

Write-Host '[3/4] Preparación terminada. Revisa el diff antes de publicar.' -ForegroundColor Green
Write-Host '[4/4] No se ejecutaron commit, push ni eliminación de recursos.' -ForegroundColor DarkYellow
