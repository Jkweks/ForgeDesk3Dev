# Run tiger-bridge as a Windows service on the shop tablet

## Context

`tiger-bridge` (`/mnt/homeNAS/Container/ForgeDesk3-dev/tiger-bridge/`) is the
small Node/Express service that owns the TigerStop's serial port and the
Zebra printer's TCP socket on the shop-floor Windows PC the saw is wired
into. Today it only runs as a foreground `npm start` process someone
launches by hand in a console window — if that console is closed, the PC
reboots, or the shop loses power, nobody notices until the saw stops
responding and someone has to walk over and manually restart it. There's no
supervision at all: no auto-restart on crash, no start-on-boot.

Separately, the version actually running on the tablet today is an older,
unversioned copy (`/mnt/homeNAS/Container/cutflow/tiger-bridge`) with **no
auth** — no `BRIDGE_TOKEN` check, no `ALLOWED_CLIENT_IPS` allowlist. The
repo's `tiger-bridge/` (absorbed into version control per
`docs/plans/absorb-cutflow-into-forgedesk.md`) already has both, but that
cutover was flagged as a manual step never done. Doing the service
conversion is a natural point to also do that cutover, so the shop tablet
ends up running the secured, version-controlled copy instead of the old one.

Goal: tiger-bridge runs as a true Windows service — starts automatically at
boot with no one logged in, restarts itself if the process crashes, and logs
to a file instead of a console window that can be closed by accident.

## Approach: NSSM (Non-Sucking Service Manager)

NSSM wraps an arbitrary executable (here, `node.exe server.js`) as a real
Windows service — handles start-at-boot, auto-restart-on-crash, and
redirecting stdout/stderr to log files, without requiring any code changes
to `server.js` (no `node-windows`/`win-service` wrapper library needed,
since the bridge is a config-driven dotenv process that doesn't need to know
it's running as a service). This is the standard, low-maintenance way to
service-ify a plain Node CLI on Windows.

## Steps (performed on the shop tablet itself)

1. **Cut over to the repo's tiger-bridge copy**
   - Copy `ForgeDesk3-dev/tiger-bridge/` (verbatim, minus `node_modules/`) to
     the shop tablet, e.g. `C:\tiger-bridge\`, replacing the old checkout
     under the standalone `cutflow` tree.
   - `cd C:\tiger-bridge && npm install` — must be run fresh on the tablet
     itself (not copied from a dev machine), since `serialport` has native
     bindings that are platform/architecture-specific.
   - Create `.env` from `.env.example`:
     - `BRIDGE_PORT=9111` (keep existing, so `TIGER_BRIDGE_URL` in
       ForgeDesk's `.env`/`cutflow_settings.bridge_url` doesn't need to change)
     - `BRIDGE_TOKEN=<openssl rand -hex 32>` — new long random secret
     - `ALLOWED_CLIENT_IPS=<ForgeDesk app server's LAN IP>/32`
     - `SERIAL_PORT=COM3` (or whatever the TigerStop actually enumerates as —
       confirm in Device Manager, don't assume it matches the old deployment)
     - `BAUD_RATE=57600`, `PRINTER_HOST=192.168.1.50`, `PRINTER_PORT=9100`
       (carry over the real values from the old `.env` on the tablet)
     - `MOCK_SERIAL=false`
   - Update ForgeDesk's CutFlow Bridge settings (admin.blade.php Settings tab,
     backed by `CutFlowBridgeSettingController`/`CutFlowSetting`) with the new
     `bridge_token` and `tablet_allowed_ips` so the two sides agree.

2. **Install NSSM** on the tablet (download the binary, no installer needed;
   place `nssm.exe` somewhere on `PATH`, e.g. `C:\tools\nssm.exe`).

3. **Register the service** (elevated/admin command prompt):
   ```
   nssm install TigerBridge "C:\Program Files\nodejs\node.exe" "server.js"
   nssm set TigerBridge AppDirectory "C:\tiger-bridge"
   nssm set TigerBridge AppStdout "C:\tiger-bridge\logs\service.log"
   nssm set TigerBridge AppStderr "C:\tiger-bridge\logs\service.log"
   nssm set TigerBridge AppRotateFiles 1
   nssm set TigerBridge Start SERVICE_AUTO_START
   nssm set TigerBridge AppExit Default Restart
   nssm set TigerBridge AppRestartDelay 3000
   ```
   - `AppDirectory` makes `dotenv` pick up `.env` from the right working
     directory (matches how `npm start` behaves today).
   - `AppExit Default Restart` + `AppRestartDelay` gives auto-restart on
     crash with a small backoff, mirroring the graceful-shutdown/reconnect
     behavior `server.js` already has for the serial port itself.
   - Create the `logs\` folder first (`mkdir C:\tiger-bridge\logs`) since NSSM
     won't create missing parent directories for its log path.

4. **Stop the old process, start the service**
   - Close whatever console/process is currently running the legacy
     unauthenticated copy (and remove/rename
     `/mnt/homeNAS/Container/cutflow/tiger-bridge` or just leave it stopped,
     so nobody accidentally restarts it later and causes two processes to
     fight over the same COM port).
   - `nssm start TigerBridge` (or `services.msc` → start it, or it'll
     auto-start on the next reboot anyway since `Start=SERVICE_AUTO_START`).

5. **Verify end-to-end**
   - `curl http://127.0.0.1:9111/status -H "Authorization: Bearer <BRIDGE_TOKEN>"`
     on the tablet itself — confirms the service is up and serial port opened
     (`mockSerial: false`, real connection state).
   - From the ForgeDesk app server: trigger a real move/print from the
     `/cut-station` UI (or via `TigerBridgeClient`) and confirm it reaches
     the tablet over LAN with the new token/IP allowlist in place.
   - Reboot the tablet and confirm the service comes up automatically with no
     one logged in (`services.msc` shows `TigerBridge` as `Running`,
     `curl /status` succeeds) — this is the actual point of the exercise.
   - Pull a COM cable or otherwise force a crash and confirm NSSM restarts
     the process (check `logs\service.log` for the reconnect messages
     `server.js` already logs every 3 seconds when the serial port drops).

## Notes / things to double check on-site

- Confirm the real `SERIAL_PORT` (COM number) and `PRINTER_HOST` currently in
  use on the tablet before writing the new `.env` — don't assume they match
  the addendum doc's example values.
- This is all manual, on-machine work (remote hands or a walk-up to the shop
  tablet) — nothing here is automatable from this repo/session.
- No ForgeDesk server-side code changes are needed; this is purely an
  ops/deployment task on the tablet plus (optionally) rotating the bridge
  token/IP allowlist values in the admin Settings UI already shipped in
  commit `63b792c`.
