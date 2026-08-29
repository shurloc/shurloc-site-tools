#Requires -Modules @{ModuleName="Microsoft.PowerShell.Archive"; ModuleVersion="1.2.5"}

# There is a known bug that casues ZIPs created using Microsoft.PowerShell.Archive to create
# unix-incompatible ZIP archives.
#   Solution: https://superuser.com/a/1933458
# Install the updated Microsoft.PowerShell.Archive version using:
#   Install-Module -Name Microsoft.PowerShell.Archive -Force
# Verify the version using:
#   Get-Module -Name Microsoft.PowerShell.Archive -ListAvailable | Select-Object Name, Version

$ErrorActionPreference = "Stop"

$ProjectRoot = (Resolve-Path "$PSScriptRoot\..").Path
$PluginName  = Split-Path $ProjectRoot -Leaf

$BuildRoot  = Join-Path $ProjectRoot "build\dist"
$PluginRoot = Join-Path $BuildRoot $PluginName
$ZipFile    = Join-Path $BuildRoot "$PluginName.zip"

Write-Host ""
Write-Host "Building $PluginName..."
Write-Host ""

#
# Clean previous build.
#
if (Test-Path $BuildRoot) {
    Remove-Item $BuildRoot -Recurse -Force
}

New-Item `
    -ItemType Directory `
    -Force `
    -Path $BuildRoot | Out-Null

#
# Copy the project, excluding development files.
#
$ExcludedDirectories = @(
    ".git",
    ".github",
    ".vscode",
    ".phpunit.cache",
    "build",
    "tests",
    "vendor",
    "bin"
)

$ExcludedFiles = @(
    ".editorconfig",
    ".gitattributes",
    ".gitignore",
    ".phpunit.result.cache",
    "composer.json",
    "composer.lock",
    "phpcs.xml",
    "phpcs.xml.dist",
    "phpunit.xml",
    "phpunit.xml.dist",
    "phpstan.neon",
    "phpstan.neon.dist",
    "README-development.md",
    "TODO.md",
    ".gitkeep",
    "CHANGELOG.md"
)

$RoboCopyArguments = @(
    $ProjectRoot
    $PluginRoot
    "/E"
    "/R:2"
    "/W:1"
    "/NFL"
    "/NDL"
    "/NJH"
    "/NJS"
    "/NP"
)

if ($ExcludedDirectories.Count -gt 0) {
    $RoboCopyArguments += "/XD"
    $RoboCopyArguments += $ExcludedDirectories
}

if ($ExcludedFiles.Count -gt 0) {
    $RoboCopyArguments += "/XF"
    $RoboCopyArguments += $ExcludedFiles
}

& robocopy @RoboCopyArguments

#
# Robocopy exit codes 0-7 indicate success.
#
if ($LASTEXITCODE -gt 7) {
    throw "Robocopy failed with exit code $LASTEXITCODE."
}

#
# Verify required plugin files.
#
$PluginBootstrapFile = "$PluginName.php"
$PluginBootstrapPath = Join-Path $PluginRoot $PluginBootstrapFile

if (-not (Test-Path $PluginBootstrapPath)) {
    throw "Plugin bootstrap file '$PluginBootstrapFile' was not copied."
}

if (-not (Test-Path (Join-Path $PluginRoot "includes"))) {
    throw "The 'includes' directory was not copied."
}

#
# Remove any previous ZIP.
#
if (Test-Path $ZipFile) {
    Remove-Item $ZipFile -Force
}

#
# Create ZIP archive.
#
Compress-Archive `
    -Path (Join-Path $BuildRoot $PluginName) `
    -DestinationPath $ZipFile `
    -Force

Write-Host ""
Write-Host "Build package contents:"
Write-Host ""
tar -tf $ZipFile
Write-Host ""
Write-Host "Build complete."
Write-Host ""
Write-Host "Folder:"
Write-Host "  $PluginRoot"
Write-Host ""
Write-Host "ZIP:"
Write-Host "  $ZipFile"
Write-Host ""
