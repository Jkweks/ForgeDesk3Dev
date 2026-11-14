# fdrive-watcher

Tells ForgeDesk when files appear or change on the F: share. Runs on the Windows file server (or any PC with
the share mounted), has **no dependencies** beyond Node 18+, and is **read-only**: it never writes to the share.
ForgeDesk (`POST /api/v1/ingest/fdrive`) decides what each file is and whether to import it
(`docs/plans/pm-suite-integration.md`, section 4a).

## How it behaves
- Watches `WATCH_ROOTS` recursively for `.xls/.xlsx/.xlsm/.pdf` (override with `EXTENSIONS`).
- Debounces bursts of events, then waits until the file's size and mtime stop changing (`SETTLE_MS`) so a
  slow copy of a large PDF is not reported half-written.
- Hashes the file (SHA-256) and posts `{path, size, mtime, sha256, event}` with the `X-Ingest-Token` header.
- Retries network errors and 5xx with backoff; gives up immediately on a 4xx (bad token or payload).
- ForgeDesk dedupes by hash, so duplicate or repeated events are harmless. A nightly `pm:scan-fdrive` in
  ForgeDesk reconciles anything this service missed (service down, reboot).

## Configure and run
```
set WATCH_ROOTS=F:\Engineering\Jobs In PROGRESS|F:\Engineering\Estimates
set INGEST_URL=https://forgedesk.example.com/api/v1/ingest/fdrive
set INGEST_TOKEN=<same value as FDRIVE_INGEST_TOKEN in ForgeDesk's .env>
node watcher.js
```
ForgeDesk side: set `FDRIVE_INGEST_TOKEN` (and `FDRIVE_SCAN_ROOTS`, `|`-separated paths as seen by the ForgeDesk
container, for the nightly scan).

## Run as a Windows service
`service\install-windows.ps1` registers a scheduled task that starts at logon or boot and restarts on failure
(same approach as tiger-bridge). Run it from an elevated PowerShell. **Not yet tested on a Windows machine.**

## Tests
`npm test` (Node 18+; runs `node --test`): hashing, settle logic, retry rules and the posted payload.
