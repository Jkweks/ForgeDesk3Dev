# Stops and removes the TigerBridge scheduled task (and its firewall rule).
# Run from an elevated PowerShell.
param([string]$TaskName = 'TigerBridge')

$ErrorActionPreference = 'Stop'

if (Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue) {
    Stop-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    Write-Host "Removed scheduled task '$TaskName'."
} else {
    Write-Host "No scheduled task named '$TaskName'."
}

Get-NetFirewallRule -DisplayName "$TaskName (TCP *" -ErrorAction SilentlyContinue | Remove-NetFirewallRule
# Stopping the task ends its whole process tree, so node releases the COM port.
