$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
Set-Location -LiteralPath $projectRoot
New-Item -ItemType Directory -Force -Path '.local\downloads', '.local\logs' | Out-Null
if (-not (Test-Path -LiteralPath '.local\postgres\bin\pg_ctl.exe')) {
    $zip = Join-Path $projectRoot '.local\downloads\postgresql.zip'
    if (-not (Test-Path -LiteralPath $zip)) {
        Invoke-WebRequest -Uri 'https://get.enterprisedb.com/postgresql/postgresql-17.11-5-windows-x64-binaries.zip' -OutFile $zip
    }
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [IO.Compression.ZipFile]::OpenRead($zip)
    try {
        foreach ($entry in $archive.Entries) {
            if ($entry.FullName -notmatch '^pgsql/(bin|lib|share)/') { continue }
            $relative = $entry.FullName.Substring(6).Replace('/', [IO.Path]::DirectorySeparatorChar)
            $destination = [IO.Path]::GetFullPath((Join-Path $projectRoot ".local\postgres\$relative"))
            $allowedRoot = [IO.Path]::GetFullPath((Join-Path $projectRoot '.local\postgres')) + [IO.Path]::DirectorySeparatorChar
            if (-not $destination.StartsWith($allowedRoot, [StringComparison]::OrdinalIgnoreCase)) { throw 'Ruta invalida en ZIP.' }
            if ($entry.Name -eq '') { New-Item -ItemType Directory -Force -Path $destination | Out-Null; continue }
            New-Item -ItemType Directory -Force -Path (Split-Path $destination) | Out-Null
            [IO.Compression.ZipFileExtensions]::ExtractToFile($entry, $destination, $true)
        }
    } finally { $archive.Dispose() }
}
if (-not (Test-Path -LiteralPath '.env')) {
    $dbPassword = [Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')
    $faceToken = [Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')
    $settings = [IO.File]::ReadAllText((Join-Path $projectRoot '.env.example'))
    $settings = $settings.Replace('DB_PASSWORD=', "DB_PASSWORD=$dbPassword").Replace('FACE_PHOTO_CHECK_TOKEN=', "FACE_PHOTO_CHECK_TOKEN=$faceToken")
    [IO.File]::WriteAllText((Join-Path $projectRoot '.env'), $settings)
}
# Enable the PHP driver with an original backup; no DB configuration is changed in Laragon.
$ini = (& php -r 'echo php_ini_loaded_file();')
if (-not $ini -or $LASTEXITCODE -ne 0) { throw 'PHP debe estar disponible en PATH con php.ini.' }
$configText = [IO.File]::ReadAllText($ini)
foreach ($extension in @('pdo_pgsql', 'pgsql')) {
    if ($configText -notmatch "(?m)^\s*extension\s*=\s*(php_)?$extension(\.dll)?\s*$") {
        $backup = Join-Path $projectRoot '.local\php.ini.before-pgsql'
        if (-not (Test-Path -LiteralPath $backup)) { Copy-Item -LiteralPath $ini -Destination $backup }
        $configText += "`nextension=$extension`n"
    }
}
[IO.File]::WriteAllText($ini, $configText, [Text.UTF8Encoding]::new($false))
& php -r 'exit(extension_loaded("pdo_pgsql") ? 0 : 1);'
if ($LASTEXITCODE -ne 0) { throw 'PHP no pudo cargar pdo_pgsql.' }
& composer install --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'Composer fallo.' }
if ([IO.File]::ReadAllText((Join-Path $projectRoot '.env')) -match '(?m)^APP_KEY=\s*$') {
    & php artisan key:generate --force
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo generar APP_KEY.' }
}
$localSettings = [IO.File]::ReadAllText((Join-Path $projectRoot '.env'))
$testSettings = $localSettings.Replace('APP_ENV=local', 'APP_ENV=testing').Replace('DB_DATABASE=vecino', 'DB_DATABASE=vecino_test')
[IO.File]::WriteAllText((Join-Path $projectRoot '.env.testing'), $testSettings)
$tokenLine = ($localSettings -split "`r?`n" | Where-Object { $_.StartsWith('FACE_PHOTO_CHECK_TOKEN=') })
[IO.File]::WriteAllText((Join-Path $projectRoot 'modeloJose\.env'), "$tokenLine`nFACE_PHOTO_CHECK_PORT=8782`nFACE_CPU_THREADS=2`n")
& (Join-Path $PSScriptRoot 'pg.ps1') init
& php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Migraciones fallidas.' }
if (-not (Test-Path -LiteralPath 'modeloJose\.venv\Scripts\python.exe')) { & uv venv --python 3.13 modeloJose/.venv }
& uv pip install --python modeloJose/.venv/Scripts/python.exe -r modeloJose/requirements-dev.txt
if ($LASTEXITCODE -ne 0) { throw 'Dependencias Python fallidas.' }
& npm install --no-audit --no-fund
if ($LASTEXITCODE -ne 0) { throw 'Dependencias frontend fallidas.' }
& npm run build
if ($LASTEXITCODE -ne 0) { throw 'Build frontend fallido.' }
Write-Host 'Entorno listo. Inicia con .\scripts\start-local.ps1'
