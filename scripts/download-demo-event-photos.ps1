$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$directory = Join-Path $projectRoot 'database\data\demo\events'
$manifestPath = Join-Path $directory 'manifest.json'
$manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
foreach ($image in $manifest.images) {
    $destination = Join-Path $directory $image.file
    if (-not (Test-Path -LiteralPath $destination)) {
        $url = "https://images.pexels.com/photos/$($image.id)/pexels-photo-$($image.id).jpeg?auto=compress&cs=tinysrgb&w=960"
        Invoke-WebRequest -Uri $url -OutFile $destination
    }
    $hash = (Get-FileHash -LiteralPath $destination -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($image.PSObject.Properties.Name -contains 'sha256') {
        if ($image.sha256 -ne $hash) { throw "La imagen $($image.file) no coincide con su SHA256." }
    } else {
        $image | Add-Member -NotePropertyName sha256 -NotePropertyValue $hash
    }
    Write-Output "Lista: $($image.file)"
}
[IO.File]::WriteAllText($manifestPath, ($manifest | ConvertTo-Json -Depth 6), [Text.UTF8Encoding]::new($false))
