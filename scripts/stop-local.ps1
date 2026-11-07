$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
foreach ($name in @('laravel','face')) {
    $pidFile = Join-Path $projectRoot ".local\$name.pid"
    if (-not (Test-Path -LiteralPath $pidFile)) { continue }
    $servicePid = [int][IO.File]::ReadAllText($pidFile)
    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $servicePid"
    if ($process) {
        $expected = if ($name -eq 'face') { 'main.py' } else { 'server.php' }
        if ($process.CommandLine -notlike "*$expected*") { throw "El PID guardado de $name pertenece a otro proceso." }
        # Windows launchers may have multiple levels of children. Stop the whole
        # validated tree at once so a child cannot survive or be restarted.
        $allProcesses = Get-CimInstance Win32_Process
        $tree = [Collections.Generic.List[int]]::new()
        $tree.Add($servicePid)
        for ($index = 0; $index -lt $tree.Count; $index++) {
            foreach ($child in $allProcesses | Where-Object { $_.ParentProcessId -eq $tree[$index] }) {
                if (-not $tree.Contains([int]$child.ProcessId)) { $tree.Add([int]$child.ProcessId) }
            }
        }
        # A launcher can exit after its child is killed, making taskkill report
        # an already-exited parent. Verify the observed tree instead of its code.
        & taskkill.exe /PID $servicePid /T /F *> $null
        foreach ($processId in $tree) {
            if (Get-Process -Id $processId -ErrorAction SilentlyContinue) { throw "No se pudo detener $name (PID $processId)." }
        }
    }
    Remove-Item -LiteralPath $pidFile
}
& (Join-Path $PSScriptRoot 'pg.ps1') stop
