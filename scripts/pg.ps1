param([ValidateSet('init','start','stop','status')][string]$Action = 'start')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$bin = Join-Path $projectRoot '.local\postgres\bin'
$data = Join-Path $projectRoot '.local\pgdata'
$log = Join-Path $projectRoot '.local\logs\postgres.log'
$envFile = Join-Path $projectRoot '.env'

function Invoke-Pg([string]$Name, [string[]]$Arguments) {
    & (Join-Path $bin "$Name.exe") @Arguments
    if ($LASTEXITCODE -ne 0) { throw "$Name fallo con codigo $LASTEXITCODE" }
}
function Read-Setting([string]$Name) {
    $line = Get-Content -LiteralPath $envFile | Where-Object { $_.StartsWith("$Name=") } | Select-Object -First 1
    if (-not $line) { throw "Falta $Name en .env" }
    return $line.Substring($Name.Length + 1).Trim('"')
}
if (-not (Test-Path -LiteralPath (Join-Path $bin 'pg_ctl.exe'))) {
    throw 'Primero ejecuta scripts\setup-local.ps1 para descargar PostgreSQL.'
}
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null
if ($Action -eq 'init') {
    $adminPasswordFile = Join-Path $projectRoot '.local\postgres-admin.password'
    if (-not (Test-Path -LiteralPath (Join-Path $data 'PG_VERSION'))) {
        if (-not (Test-Path -LiteralPath $adminPasswordFile)) {
            [IO.File]::WriteAllText($adminPasswordFile, [Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N'))
        }
        Invoke-Pg 'initdb' @('-D', $data, '-U', 'postgres', '-A', 'scram-sha-256', '--encoding=UTF8', '--locale=C', "--pwfile=$adminPasswordFile")
        [IO.File]::AppendAllText((Join-Path $data 'postgresql.conf'), "`nlisten_addresses = '127.0.0.1'`nport = 5433`n")
    }
    & (Join-Path $bin 'pg_ctl.exe') -D $data status *> $null
    if ($LASTEXITCODE -ne 0) { Invoke-Pg 'pg_ctl' @('-D', $data, '-l', $log, '-w', 'start') }
    $savedPassword = $env:PGPASSWORD
    try {
        $env:PGPASSWORD = [IO.File]::ReadAllText($adminPasswordFile).Trim()
        $dbUser = Read-Setting 'DB_USERNAME'
        $dbPassword = Read-Setting 'DB_PASSWORD'
        # Setup-generated values only, never interpolate arbitrary shell/SQL input.
        if ($dbUser -ne 'vecino' -or $dbPassword -notmatch '^[a-f0-9]{64}$') { throw 'Credenciales locales inesperadas.' }
        $common = @('-h','127.0.0.1','-p','5433','-U','postgres','-d','postgres','-v','ON_ERROR_STOP=1')
        $exists = & (Join-Path $bin 'psql.exe') @common -tAc "SELECT 1 FROM pg_roles WHERE rolname = 'vecino'"
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo consultar PostgreSQL.' }
        if ($exists -ne '1') { Invoke-Pg 'psql' ($common + @('-c', "CREATE ROLE vecino LOGIN PASSWORD '$dbPassword'")) }
        foreach ($dbName in @('vecino', 'vecino_test')) {
            $exists = & (Join-Path $bin 'psql.exe') @common -tAc "SELECT 1 FROM pg_database WHERE datname = '$dbName'"
            if ($LASTEXITCODE -ne 0) { throw 'No se pudo consultar la base de datos.' }
            if ($exists -ne '1') { Invoke-Pg 'createdb' @('-h','127.0.0.1','-p','5433','-U','postgres','-O','vecino','-E','UTF8','-T','template0',$dbName) }
        }
    } finally { $env:PGPASSWORD = $savedPassword }
} elseif ($Action -eq 'start') {
    & (Join-Path $bin 'pg_ctl.exe') -D $data status *> $null
    if ($LASTEXITCODE -ne 0) { Invoke-Pg 'pg_ctl' @('-D', $data, '-l', $log, '-w', 'start') }
} elseif ($Action -eq 'stop') {
    Invoke-Pg 'pg_ctl' @('-D', $data, '-m', 'fast', '-w', 'stop')
} else {
    Invoke-Pg 'pg_ctl' @('-D', $data, 'status')
}
