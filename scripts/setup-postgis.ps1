param()
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$postgresRoot = Join-Path $projectRoot '.local\postgres'
$archive = Join-Path $projectRoot '.local\downloads\postgis-pg17.zip'
$expectedHash = '7BA180EE2A352987B9A2F194673652C59483B55852295CCF401DCECCD8765425'
if (-not (Test-Path -LiteralPath (Join-Path $postgresRoot 'share\extension\postgis.control'))) {
    New-Item -ItemType Directory -Force -Path (Split-Path $archive) | Out-Null
    if (-not (Test-Path -LiteralPath $archive)) {
        Invoke-WebRequest -Uri 'https://download.osgeo.org/postgis/windows/pg17/postgis-bundle-pg17-3.6.2x64.zip' -OutFile $archive
    }
    if ((Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash -ne $expectedHash) { throw 'El paquete PostGIS no coincide con su SHA256.' }
    $extractRoot = Join-Path $projectRoot '.local\postgis-unpacked'
    Expand-Archive -LiteralPath $archive -DestinationPath $extractRoot -Force
    $sourceRoot = Join-Path $extractRoot 'postgis-bundle-pg17-3.6.2x64'
    foreach ($folder in @('bin','lib','share')) {
        foreach ($file in Get-ChildItem -LiteralPath (Join-Path $sourceRoot $folder) -Recurse -File) {
            $relative = $file.FullName.Substring($sourceRoot.Length + 1)
            $destination = Join-Path $postgresRoot $relative
            # Do not overwrite DLLs used by the running portable PostgreSQL.
            if (-not (Test-Path -LiteralPath $destination)) {
                New-Item -ItemType Directory -Force -Path (Split-Path $destination) | Out-Null
                Copy-Item -LiteralPath $file.FullName -Destination $destination
            }
        }
    }
}
& (Join-Path $PSScriptRoot 'pg.ps1') start
$savedPassword = $env:PGPASSWORD
try {
    $env:PGPASSWORD = [IO.File]::ReadAllText((Join-Path $projectRoot '.local\postgres-admin.password')).Trim()
    foreach ($database in @('vecino','vecino_test')) {
        & (Join-Path $postgresRoot 'bin\psql.exe') -h 127.0.0.1 -p 5433 -U postgres -d $database -v ON_ERROR_STOP=1 -c 'CREATE EXTENSION IF NOT EXISTS postgis;'
        if ($LASTEXITCODE -ne 0) { throw "No se pudo activar PostGIS en $database." }
    }
} finally { $env:PGPASSWORD = $savedPassword }
Write-Host 'PostGIS listo en vecino y vecino_test.'
