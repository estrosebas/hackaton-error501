$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
& (Join-Path $PSScriptRoot 'pg.ps1') start
New-Item -ItemType Directory -Force -Path (Join-Path $projectRoot '.local\logs') | Out-Null
foreach ($service in @(
    @{ Name='face'; Port=8782; File=(Join-Path $projectRoot 'modeloJose\.venv\Scripts\python.exe'); Arguments=@('main.py','photo-check'); Directory=(Join-Path $projectRoot 'modeloJose') },
    @{ Name='laravel'; Port=8000; File=(Get-Command php).Source; Arguments=@('-S','127.0.0.1:8000',(Join-Path $projectRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php')); Directory=(Join-Path $projectRoot 'public') }
)) {
    $pidFile = Join-Path $projectRoot ".local\$($service.Name).pid"
    if (Get-NetTCPConnection -LocalPort $service.Port -State Listen -ErrorAction SilentlyContinue) {
        if (-not (Test-Path -LiteralPath $pidFile)) { throw "Puerto $($service.Port) ocupado por otro proceso." }
        $savedProcess = Get-Process -Id ([int][IO.File]::ReadAllText($pidFile)) -ErrorAction SilentlyContinue
        if (-not $savedProcess) { throw "El PID de $($service.Name) ya no existe, pero su puerto esta ocupado." }
        Write-Host "$($service.Name) ya esta escuchando en $($service.Port)."
        continue
    }
    $process = Start-Process -FilePath $service.File -ArgumentList $service.Arguments -WorkingDirectory $service.Directory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $projectRoot ".local\logs\$($service.Name).log") -RedirectStandardError (Join-Path $projectRoot ".local\logs\$($service.Name).err.log")
    [IO.File]::WriteAllText($pidFile, [string]$process.Id)
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
Write-Host 'Aplicacion: http://127.0.0.1:8000 | Registro: http://127.0.0.1:8000/registro'
