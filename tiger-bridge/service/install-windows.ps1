<#
.SYNOPSIS
  Registers tiger-bridge as a Scheduled Task that starts automatically and
  restarts itself if it crashes.

.PARAMETER Trigger
  Logon   (default) start when the current user logs in, running as that user.
  Startup start at boot, before anyone logs in, running as SYSTEM.

.PARAMETER OpenFirewall
  Also add an inbound Windows Firewall rule for the bridge port.

.PARAMETER AllowedFrom
  With -OpenFirewall: remote address(es) allowed to connect (the ForgeDesk
  server's LAN IP). Defaults to LocalSubnet.

Run from an elevated (Administrator) PowerShell:
  powershell -ExecutionPolicy Bypass -File .\service\install-windows.ps1
#>
param(
    [ValidateSet('Logon', 'Startup')][string]$Trigger = 'Logon',
    [switch]$OpenFirewall,
    [string[]]$AllowedFrom = @('LocalSubnet'),
    [string]$TaskName = 'TigerBridge'
)

$ErrorActionPreference = 'Stop'

$principalCheck = [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
if (-not $principalCheck.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Run this from an elevated (Administrator) PowerShell.'
}

$root = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $root '.env'

if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
    throw 'node was not found on PATH. Install Node.js 20+ for all users first.'
}
if (-not (Test-Path $envFile)) {
    throw ".env not found at $envFile. Copy .env.example to .env and set SERIAL_PORT, PRINTER_HOST and BRIDGE_TOKEN first."
}
if (-not (Select-String -Path $envFile -Pattern '^\s*BRIDGE_TOKEN\s*=\s*\S' -Quiet)) {
    throw 'BRIDGE_TOKEN is empty in .env - the bridge refuses to start without it.'
}

if (-not (Test-Path (Join-Path $root 'node_modules'))) {
    Write-Host 'Installing dependencies (npm install --omit=dev)...'
    Push-Location $root
    try { npm install --omit=dev; if ($LASTEXITCODE -ne 0) { throw 'npm install failed' } } finally { Pop-Location }
}

$runner = Join-Path $PSScriptRoot 'run-bridge.ps1'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' `
    -Argument "-NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$runner`"" `
    -WorkingDirectory $root

if ($Trigger -eq 'Startup') {
    $taskTrigger = New-ScheduledTaskTrigger -AtStartup
    $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
} else {
    $me = [Security.Principal.WindowsIdentity]::GetCurrent().Name
    $taskTrigger = New-ScheduledTaskTrigger -AtLogOn -User $me
    $principal = New-ScheduledTaskPrincipal -UserId $me -LogonType Interactive -RunLevel Limited
}

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -ExecutionTimeLimit ([TimeSpan]::Zero) `
    -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) `
    -MultipleInstances IgnoreNew

if (Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
}
Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $taskTrigger `
    -Principal $principal -Settings $settings `
    -Description 'tiger-bridge: TigerStop serial + Zebra printer bridge for ForgeDesk CutFlow' | Out-Null

if ($OpenFirewall) {
    $port = 9111
    $m = Select-String -Path $envFile -Pattern '^\s*BRIDGE_PORT\s*=\s*(\d+)' | Select-Object -First 1
    if ($m) { $port = [int]$m.Matches[0].Groups[1].Value }
    $rule = "$TaskName (TCP $port)"
    Get-NetFirewallRule -DisplayName $rule -ErrorAction SilentlyContinue | Remove-NetFirewallRule
    New-NetFirewallRule -DisplayName $rule -Direction Inbound -Action Allow -Protocol TCP `
        -LocalPort $port -RemoteAddress $AllowedFrom | Out-Null
    Write-Host "Firewall: allowed TCP $port from $($AllowedFrom -join ', ')"
}

Start-ScheduledTask -TaskName $TaskName
Write-Host "Installed scheduled task '$TaskName' (trigger: $Trigger) and started it."
Write-Host "Log: $(Join-Path $root 'logs\bridge.log')"
Write-Host "Check:  Invoke-RestMethod http://127.0.0.1:9111/status"
