# Script to validate render.yaml configuration using the local Render CLI
$ExePath = Join-Path $PSScriptRoot ".render-cli\render.exe"

if (!(Test-Path $ExePath)) {
    Write-Error "Render CLI is not installed. Please run .\install-render-cli.ps1 first."
    exit 1
}

Write-Host "Validating render.yaml configuration..." -ForegroundColor Cyan
& $ExePath blueprints validate
