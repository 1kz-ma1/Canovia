param([Parameter(Mandatory=$true)][string]$Repository)
$ErrorActionPreference = 'Stop'
$manifest = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'manifest.json') -Raw | ConvertFrom-Json
$mismatches = @()
foreach ($file in $manifest.files) {
    $target = Join-Path $Repository $file.path
    if ($null -eq $file.base_sha256) {
        if (Test-Path -LiteralPath $target) { $mismatches += $file.path }
    } elseif (!(Test-Path -LiteralPath $target) -or ((Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash.ToLowerInvariant() -ne $file.base_sha256)) {
        $mismatches += $file.path
    }
}
if ($mismatches.Count -gt 0) {
    Write-Output 'Review these files before applying (content or line endings differ):'
    $mismatches | Write-Output
    exit 1
}
Write-Output 'All patch targets match the V34 baseline. No files were changed.'
