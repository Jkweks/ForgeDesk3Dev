# Plan: CutFlow offline mode

Status: **proposal, scoped 2026-11-17, not started.**

## Decisions (from the shop)

- **Production ForgeDesk is on-site; dev is off-site.** So "offline" is not a WAN outage. It is the server being unreachable for a short time: container restart, deploy, a LAN or Wi-Fi hiccup.
- **Outages are minutes, half an hour at most.**
- **Offline use = keep working on existing jobs that are not cut or are in process.** That includes developing cut plans (building sticks) from them. It does not include importing or editing cut lists, recuts, settings, or reports.
- **The tablet is a Surface (Windows).** Chrome/Edge on Windows fully supports service workers, IndexedDB, and wake lock. `http://localhost` is a secure context, which matters below.

## Goal

If the ForgeDesk server is unreachable, the operator keeps cutting from the jobs already loaded on the tablet. Nothing cut offline is lost, and everything syncs to ForgeDesk automatically when the server returns.

## Why it needs a real design

`docs/plans/cut-station-pwa.md` made `/cut-station` installable but deliberately not offline-capable: `App\Livewire\CutFlow\Dashboard` (~1,800 lines) is a server round trip for every keypad press, stick build, move, print and cut. With no server there is no UI logic. Offline needs the cut logic to run on the tablet.

## Two cheaper ways to attack the problem first

Because the outages are short and the server is on-site, do these **before or alongside** any offline build, since they remove most of the pain:

1. **Avoid the outage.** Check what actually takes the server down. `scripts/entrypoint.sh` runs `migrate --force` on every container start, and deploys restart the app container. Deploy outside shop hours or during breaks, make restarts fast, and put a status line on the cut station ("server restarting, back in ~1 min").
2. **Make the existing page ride out brief blips.** Livewire already retries poorly. A 20-second friendly "reconnecting…" overlay that holds the operator's place and resumes when the server answers costs almost nothing and covers most sub-minute drops.

Offline mode (below) is for the longer cases, up to ~30 minutes, where the operator needs to keep cutting.

## Architecture: run it on the tablet itself

The Surface is almost certainly also the tiger-bridge PC (per `docs/plans/tiger-bridge-windows-service.md`, the bridge runs on the shop tablet that the saw and printer are wired to). Confirm this. If it is, the bridge and the offline UI are **the same machine**, which removes the hardest problem of the browser-only design:

- The offline UI is served by tiger-bridge at `http://localhost:9111/offline/`. A page on `localhost` calling the bridge on `localhost` has no HTTPS/mixed-content or CORS problem, and no tablet-IP allowlist question.
- The bridge is already a supervised Windows service that starts at boot, so the offline store survives reboots and power loss.

If the Surface is not the bridge PC, fall back to serving the offline UI from the bridge over the LAN. That adds the mixed-content and allowlist work, so this is the first thing to verify.

## What the offline mode does

### Pre-sync (while online)
The bridge pulls a snapshot from ForgeDesk every few minutes and on demand, for the jobs the operator has selected or that are not-cut/in-process:
- Cut jobs and parts: `qty_remaining`, finish, phase, work order, dimensions, profile.
- The active stick session and its items, so a stick in progress survives the switch.
- Stock-length and kerf settings, and the rule-adjusted piece list (the output of `CutlistRuleEngine` is already stored on the parts, so the offline side does not need to port the rule engine).
- PIN hashes (bcrypt only) of active users with a `fab_pin`.
- New ForgeDesk endpoint: `GET /api/v1/cutflow/offline-snapshot`, token-authenticated, read-only.

Stored in SQLite on the Surface (`better-sqlite3`; native module, installed on the PC itself like `serialport`).

### Offline feature set (v1)
Supported:
- PIN sign-in from cached hashes.
- Pick from the snapshotted not-cut/in-process jobs.
- **Develop a cut plan:** build a stick for a profile (the `CutPlanner` + `StickPacker` logic, about 700 lines of PHP, ported to JS/Node).
- Move, cut, print label, manual-entry cut, skip a piece.
- Drop labels.

Disabled with a clear reason: new job import, cut-list edits, recut, settings, reports, and any job that was not in the snapshot.

The offline UI always shows an **OFFLINE** banner and "data as of HH:MM". The operator must never be unsure which mode they are in.

### Switching modes
- The service worker already detects a failed navigation to `/cut-station`. Instead of the static "can't reach server" page it sends the tablet to the offline UI on localhost.
- The offline UI polls a tiny `/cut-station/ping`. When the server answers, it shows "Syncing N cuts…" and returns the operator to the normal page once the outbox is empty.
- An operator-initiated "Go offline now" button is worth having for known restarts.

### Recording cuts and the outbox
Every offline action (`cut_recorded`, `piece_skipped`, `stick_started`, `stick_ended`) is written locally as an event with a **client-generated UUID**, in order, with a device id and sequence number.
- The cut event carries the same `uuid` that `cut_log_entries.uuid` will have on the server. Replaying a batch twice is therefore harmless: the server recognises the uuid and ignores the duplicate.
- Local `qty_remaining` is decremented from the snapshot so the operator's own view stays correct while offline.
- Labels printed offline still carry the QR for `/cut-record/{uuid}`. It will 404 until sync lands the record. In a short outage that is acceptable and should be mentioned to the shop.

### Sync back (replay)
- New endpoint `POST /api/v1/cutflow/offline-sync`: a batch of events. The server applies them in sequence order per device, one transaction per event, and returns `applied` / `duplicate` / `conflict` for each.
- **First refactor:** pull the logic in `Dashboard::recordPieceCut` (part decrement, `CutLogEntry` create, `CutConsumptionService::recordCut`, `CutStepSync::afterPartCut`) into one shared service. The Livewire path and the replay path both call it, so they cannot drift.
- The bridge deletes an event from its outbox only after the server confirms it, and retries with backoff.

### Conflicts
With on-site production and short outages these should be rare, but they must be handled:
- **Part already fully cut, or the quantity would go negative** (someone cut it from another screen): apply the cut anyway, because the metal is physically cut. Clamp `qty_remaining` at 0 and raise a **reconciliation item** for an admin. Never silently drop a real cut.
- **Part or job changed or deleted on the server** during the outage: the cut log already keeps snapshot fields (`part_name`, `job_name`, `work_order`), so record against those and flag it for review.
- **User deactivated or PIN changed:** attribute from the snapshot, flag if the user is no longer active.
- **A second station:** not supported in v1. This should sit after `cutflow-multi-station.md`, or carry a `station_id` in the events from the start if it will happen first.

### Security
- The offline UI listens on localhost only. Bridge endpoints keep `BRIDGE_TOKEN`.
- PIN hashes on the Surface are sensitive: lock down the folder, and replace the hash set on every snapshot so deactivated users disappear.
- The snapshot and sync endpoints get their own scoped token, rotatable from the existing CutFlow bridge settings screen. They must be listed correctly for `SecurityHardeningTest` (the sync endpoint is mutating).

## Phases

0. **Quick wins.** Deploy-window discipline, reconnecting overlay, confirm the Surface is the bridge PC. Almost no code.
1. **Server foundation.** Extract the shared cut-recording service; add the snapshot and sync endpoints with tests (duplicate uuid, out-of-order, negative quantity, deleted part, same result as the online path for the same cut).
2. **Bridge store and sync client.** SQLite, snapshot pull, outbox, retry/backoff. `/status` reports "snapshot age" and "outbox depth".
3. **Offline UI v1.** Sign-in, job pick, stick planner (JS port), move/cut/print, manual entry. Service-worker redirect.
4. **Reconciliation screen** in ForgeDesk admin for conflict items, plus a notification when the outbox has been stuck for more than N minutes.
5. **Shop trial.** On the real saw, deliberately stop the app container mid-stick, finish the stick offline, restart, and confirm the cut log, quantities, stock draw-down and work-order "Cut" step match an online run.

## Verification

- Server tests for the sync endpoint as above. Add one test that runs the same cut scenario through the Livewire path and the replay path and compares every resulting row.
- Port tests for the JS stick planner: feed it the same part lists as the existing `CutPlanner`/`StickPacker` PHP tests and require identical sticks. This is the main risk of a second implementation.
- Bridge tests (alongside the existing `tiger-bridge/test/`): the outbox survives a restart, a failed upload is retried, a snapshot refresh removes deactivated users.
- On-hardware test in phase 5. Run tests only through `composer test` / the documented container path, never against the live database.

## Risks

- **Two implementations of the planner.** Mitigated by the parity tests above and by keeping the offline feature set small.
- **Stale snapshot.** An edit made online just before the outage is not visible offline. Show the snapshot age, and refresh frequently.
- **Bridge PC becomes the home of offline data.** Back up the SQLite file; the service setup in `tiger-bridge-windows-service.md` already handles restart on boot.
- **Native modules on Windows** (`better-sqlite3`, like `serialport`) must be installed on the Surface itself.
- **Scope creep.** Anything beyond the v1 list stays online-only until the lifeboat has survived a real outage.

## Open questions

1. Is the Surface the same machine that runs tiger-bridge and has the saw and printer attached? (Decides localhost vs LAN.)
2. When the server drops, does the tablet usually lose Wi-Fi too, or only the server goes away? (Tells us whether the LAN link to the bridge is safe to depend on.)
3. Roughly how often does the server restart or drop per week? If it is only deploys, phase 0 alone may be enough.
4. Is a second saw/tablet (`cutflow-multi-station.md`) likely soon? If so, build `station_id` into the events now.

## Rough size

Medium to large, smaller than the first draft because there is no network-boundary or hardware-access problem. Phase 0 is hours. Phase 1 is small to medium. Phases 2 and 3 are the bulk (the stick-planner port plus the offline UI), roughly a few weeks including on-hardware testing. If phase 0 and short blips turn out to be most of the pain, stop there.
