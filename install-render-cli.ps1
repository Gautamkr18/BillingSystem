# Automated Render CLI Installer for Windows
# This script downloads and extracts the official Render CLI into a local .render-cli directory.

$InstallDir = Join-Path $PSScriptRoot ".render-cli"
$ZipFile = Join-Path $InstallDir "cli.zip"
$Version = "2.18.0"
$Url = "https://github.com/render-oss/cli/releases/download/v$Version/cli_${Version}_windows_amd64.zip"

Write-Host "Creating installation directory at: $InstallDir" -ForegroundColor Cyan
if (!(Test-Path $InstallDir)) {
    New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null
}

Write-Host "Downloading Render CLI v$Version from GitHub..." -ForegroundColor Cyan
try {
    Invoke-WebRequest -Uri $Url -OutFile $ZipFile -ErrorAction Stop
} catch {
    Write-Error "Failed to download Render CLI. Please ensure you have internet access. Error: $_"
    exit 1
}

Write-Host "Extracting archive..." -ForegroundColor Cyan
try {
    Expand-Archive -Path $ZipFile -DestinationPath $InstallDir -Force
    Remove-Item -Path $ZipFile -Force
} catch {
    Write-Error "Failed to extract Render CLI archive. Error: $_"
    exit 1
}

$ExePath = Join-Path $InstallDir "render.exe"
if (Test-Path $ExePath) {
    Write-Host "`nRender CLI installed successfully!" -ForegroundColor Green
    Write-Host "You can run it using: .\.render-cli\render.exe" -ForegroundColor Green
    Write-Host "`nTo validate your blueprint, run:" -ForegroundColor Green
    Write-Host ".\.render-cli\render.exe blueprints validate" -ForegroundColor Yellow
} else {
    Write-Error "render.exe was not found in the extracted files."
}
