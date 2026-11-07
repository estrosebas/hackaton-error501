param([switch]$Terminals)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
& (Join-Path $PSScriptRoot 'pg.ps1') start
Push-Location $projectRoot
try {
    & (Get-Command php).Source artisan demo:seed
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar el feed local.' }
} finally { Pop-Location }
New-Item -ItemType Directory -Force -Path (Join-Path $projectRoot '.local\logs') | Out-Null
& (Get-Command node).Source (Join-Path $projectRoot 'frontend\scripts\sync-face-assets.mjs')
if ($LASTEXITCODE -ne 0) { throw 'No se pudieron preparar los recursos faciales del frontend.' }
foreach ($service in @(
    @{ Name='face'; Port=8782; File=(Join-Path $projectRoot 'modeloJose\.venv\Scripts\python.exe'); Arguments=@('main.py','photo-check'); Directory=(Join-Path $projectRoot 'modeloJose') },
    @{ Name='laravel'; Port=8000; File=(Get-Command php).Source; Arguments=@('-S','127.0.0.1:8000',(Join-Path $projectRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php')); Directory=(Join-Path $projectRoot 'public') }
    @{ Name='frontend'; Port=5173; File=(Get-Command node).Source; Arguments=@('node_modules/vite/bin/vite.js','--host=127.0.0.1','--port=5173','--strictPort'); Directory=(Join-Path $projectRoot 'frontend') }
)) {
    $pidFile = Join-Path $projectRoot ".local\$($service.Name).pid"
    if (Get-NetTCPConnection -LocalPort $service.Port -State Listen -ErrorAction SilentlyContinue) {
        if (-not (Test-Path -LiteralPath $pidFile)) { throw "Puerto $($service.Port) ocupado por otro proceso." }
        $savedProcess = Get-Process -Id ([int][IO.File]::ReadAllText($pidFile)) -ErrorAction SilentlyContinue
        if (-not $savedProcess) { throw "El PID de $($service.Name) ya no existe, pero su puerto esta ocupado." }
        Write-Host "$($service.Name) ya esta escuchando en $($service.Port)."
        continue
    }
    if ($Terminals) {
        # Serialize launch settings rather than interpolate paths into shell code.
        $settings = @{ File=$service.File; Arguments=$service.Arguments; Directory=$service.Directory; PidFile=$pidFile; Name=$service.Name } | ConvertTo-Json -Compress
        $payload = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($settings))
        $command = @"
`$ErrorActionPreference = 'Stop'
`$settings = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('$payload')) | ConvertFrom-Json
`$Host.UI.RawUI.WindowTitle = 'masi - ' + `$settings.Name
`$child = Start-Process -FilePath `$settings.File -ArgumentList `$settings.Arguments -WorkingDirectory `$settings.Directory -NoNewWindow -PassThru
[IO.File]::WriteAllText(`$settings.PidFile, [string]`$child.Id)
Wait-Process -Id `$child.Id
"@
        $encoded = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($command))
        $process = Start-Process powershell.exe -ArgumentList @('-NoProfile','-NoExit','-EncodedCommand',$encoded) -WindowStyle Normal -PassThru
    } else {
        $process = Start-Process -FilePath $service.File -ArgumentList $service.Arguments -WorkingDirectory $service.Directory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $projectRoot ".local\logs\$($service.Name).log") -RedirectStandardError (Join-Path $projectRoot ".local\logs\$($service.Name).err.log")
        [IO.File]::WriteAllText($pidFile, [string]$process.Id)
    }
    $ready = $false
    for ($attempt = 0; $attempt -lt 40; $attempt++) {
        $connection = [Net.Sockets.TcpClient]::new()
        try { $connection.Connect('127.0.0.1', $service.Port); $ready = $true; break }
        catch { Start-Sleep -Milliseconds 200 }
        finally { $connection.Dispose() }
        if ($process.HasExited) { throw "$($service.Name) termino al iniciar. Revisa .local\logs." }
    }
    if (-not $ready) { throw "$($service.Name) no abrio el puerto $($service.Port). Revisa .local\logs." }
}
if ($Terminals) {
    $logPath = (Join-Path $projectRoot '.local\logs\postgres.log').Replace("'", "''")
    $command = "`$Host.UI.RawUI.WindowTitle = 'masi - PostgreSQL 5433'; Get-Content -LiteralPath '$logPath' -Tail 20 -Wait"
    $encoded = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($command))
    Start-Process powershell.exe -ArgumentList @('-NoProfile','-NoExit','-EncodedCommand',$encoded) -WindowStyle Normal | Out-Null
}
Write-Host 'Frontend: http://127.0.0.1:5173/registro | Backend: http://127.0.0.1:8000'
