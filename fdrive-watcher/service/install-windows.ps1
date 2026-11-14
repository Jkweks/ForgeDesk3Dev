<#
.SYNOPSIS
  Registers fdrive-watcher as a Scheduled Task that starts automatically and restarts itself on failure.
  NOT YET TESTED on Windows: review before use.

.PARAMETER Trigger
  Logon (default) start at the current user's logon; Startup start at boot as SYSTEM.
  The account needs READ access to the F: share (a mapped drive letter only exists for a logged-in user,
  so use a UNC path in WATCH_ROOTS when using Startup).

Run from an elevated PowerShell:
  powershell -ExecutionPolicy Bypass -File .\service\install-windows.ps1 -WatchRoots 'F:\Engineering' -IngestUrl 'https://forgedesk/api/v1/ingest/fdrive' -IngestToken '...'
#>
param(
    [Parameter(Mandatory)][string]$WatchRoots,
    [Parameter(Mandatory)][string]$IngestUrl,
    [Parameter(Mandatory)][string]$IngestToken,
    [ValidateSet('Logon', 'Startup')][string]$Trigger = 'Logon',
    [string]$TaskName = 'FdriveWatcher'
)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$node = (Get-Command node -ErrorAction Stop).Source
$cmd = "`$env:WATCH_ROOTS='$WatchRoots'; `$env:INGEST_URL='$IngestUrl'; `$env:INGEST_TOKEN='$IngestToken'; & '$node' '$root\watcher.js'"
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -WindowStyle Hidden -Command `"$cmd`"" -WorkingDirectory $root
$trig = if ($Trigger -eq 'Startup') { New-ScheduledTaskTrigger -AtStartup } else { New-ScheduledTaskTrigger -AtLogOn }
$settings = New-ScheduledTaskSettingsSet -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero) -StartWhenAvailable
$principal = if ($Trigger -eq 'Startup') { New-ScheduledTaskPrincipal -UserId 'SYSTEM' -RunLevel Highest } else { New-ScheduledTaskPrincipal -UserId $env:USERNAME -RunLevel Limited }
Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trig -Settings $settings -Principal $principal -Force | Out-Null
Start-ScheduledTask -TaskName $TaskName
Write-Host "Registered and started scheduled task '$TaskName'."
