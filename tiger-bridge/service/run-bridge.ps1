# Launched by the TigerBridge scheduled task (see install-windows.ps1).
# Runs the bridge from its own folder, appends output to logs\bridge.log, and
# rotates that log (keeps one previous file) once it passes 5 MB.
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$logDir = Join-Path $root 'logs'
New-Item -ItemType Directory -Force -Path $logDir | Out-Null
$log = Join-Path $logDir 'bridge.log'

if ((Test-Path $log) -and ((Get-Item $log).Length -gt 5MB)) {
    Move-Item -Force $log (Join-Path $logDir 'bridge.log.1')
}

$node = (Get-Command node -ErrorAction Stop).Source
"[{0}] starting tiger-bridge ({1})" -f (Get-Date -Format s), $node | Add-Content $log

# cmd handles the redirect so node's stdout/stderr are appended line by line.
& cmd.exe /c "`"$node`" server.js >> `"$log`" 2>&1"
$code = $LASTEXITCODE
"[{0}] tiger-bridge exited with code {1}" -f (Get-Date -Format s), $code | Add-Content $log
exit $code
