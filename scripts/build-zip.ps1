<#
.SYNOPSIS
    Gera o pacote de release do GVN Checkout (equivalente Windows de build-zip.sh).

.DESCRIPTION
    Empacota apenas os arquivos de runtime em dist/gvn-checkout.zip, com a
    pasta gvn-checkout/ como raiz (estrutura exigida pelo instalador do WP).
    Exclui dev/tests/.git/dist como o script .sh oficial.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts/build-zip.ps1
#>
[CmdletBinding()]
param(
    [string]$OutputDir = (Join-Path (Get-Location) 'dist'),
    [string]$ZipName = 'gvn-checkout.zip'
)

$ErrorActionPreference = 'Stop'

$PluginSlug = 'gvn-checkout'
$BuildRoot  = Join-Path ([System.IO.Path]::GetTempPath()) "$PluginSlug-build"
$Stage      = Join-Path $BuildRoot $PluginSlug
$ZipPath    = Join-Path $OutputDir $ZipName

$RuntimePaths = @(
    'assets',
    'html',
    'includes',
    'src',
    'templates',
    'languages',
    'gvn-checkout.php',
    'readme.txt',
    'readme.md',
    'uninstall.php'
)

$JunkPatterns = @(
    '.DS_Store',
    'Thumbs.db',
    '*~',
    '*.bak',
    '*.log'
)

Write-Host "==> Gerando pacote de release do ${PluginSlug}..."

if (Test-Path -LiteralPath $BuildRoot) {
    Remove-Item -LiteralPath $BuildRoot -Recurse -Force
}
if (!(Test-Path -LiteralPath $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir | Out-Null
}
New-Item -ItemType Directory -Path $Stage | Out-Null

foreach ($path in $RuntimePaths) {
    if (Test-Path -LiteralPath $path) {
        Copy-Item -LiteralPath $path -Destination $Stage -Recurse -Force
    } else {
        Write-Warning "Ignorado (ausente): $path"
    }
}

$junk = Get-ChildItem -LiteralPath $Stage -Recurse -Force -File | Where-Object {
    $name = $_.Name
    foreach ($pattern in $JunkPatterns) {
        if ($name -like $pattern) { return $true }
    }
    return $false
}
if ($junk) {
    $junk | Remove-Item -Force
}

if (Test-Path -LiteralPath $ZipPath) {
    Remove-Item -LiteralPath $ZipPath -Force
}
Compress-Archive -Path $Stage -DestinationPath $ZipPath

Remove-Item -LiteralPath $BuildRoot -Recurse -Force

$zip = Get-Item -LiteralPath $ZipPath
Write-Host "==> Pacote gerado com sucesso em: $($zip.FullName) ($([math]::Round($zip.Length / 1KB)) KB)"
