# CutFlow: multiple cut stations (TigerStop + manual saw)

Status: **idea / not started** (written 2026-10-09). Revisit later.

## Goal

Run two CutFlow instances side by side:

- **Saw 1** — automated TigerStop: CutFlow moves the stop and prints labels.
- **Saw 2** — manually set saw: CutFlow shows the target length and prints the label; operator sets the stop by hand.
- Later, Saw 2 gets its own TigerStop + controller + printer, so the design must let a station change `kind` without a rewrite.
- Separation of control, and no conflicts when both saws cut the same job.

## What is singleton today

1. **One bridge/printer.** `CutFlowSetting` (single row) holds `bridge_url`, `bridge_token`, `bridge_fake`, `cut_sensor_active`, `tablet_allowed_ips`. `TigerBridgeClient` reads it in its constructor; every move/print/status/sensor call goes to that one URL.
2. **One active stick.** `Dashboard::mount()` (`app/Livewire/CutFlow/Dashboard.php` ~L161) loads `StickSession::where('status','active')->whereNull('cancelled_at')->latest()->first()`. Two tablets would share it.
3. **Tablet IP allowlist** is one global list (`config/cutflow.php`, `CutFlowSetting::tabletAllowedIps()`).
4. Session state (`cutflow_active_job_ids`, crew, operator) is per browser session — already per-device, fine.

## Already safe across two saws

- `CutPlanner` availability = `qty_remaining` minus qty pending on built sticks, globally — Saw B can't be handed a part Saw A has on an open stick.
- `$item->part()->decrement('qty_remaining')` is an atomic SQL decrement.
- Stock consumption (`CutConsumptionService`) is an append-only ledger.

## Plan

1. **`cut_stations` table** (cutflow DB): name, kind (`tigerstop`|`manual`), bridge_url, bridge_token, bridge_fake, cut_sensor_active, tablet_allowed_ips, optional printer endpoint. `CutFlowSetting` keeps only global values (kerf, stock length, optimizer). Migrate current bridge values into a "Saw 1" row.
2. **`station_id`** on `stick_sessions` and `cut_log_entries`; backfill existing rows with station 1. Lets history/labels/reports say which saw cut a piece.
3. **Scope Dashboard by station.** Route like `/cut-station/{station}` (PWA manifest/sw already scoped under `/cut-station`, so each tablet installs its own). Then: active-stick lookup filtered by `station_id`; `TigerBridgeClient` constructed from the station; IP allowlist per station.
4. **Manual mode** (station flag): skip `move()`, position-match and range checks, and the cut-complete sensor; show target length, print label, operator confirms cut. Upgrading to a second TigerStop = change `kind` + set bridge URL + run a second tiger-bridge.
5. **Printing.** Manual saw needs a print endpoint: second tiger-bridge instance doing only `/print`, or a print-only mode. **Check the tiger-bridge repo (separate Node app, not looked at yet)** for multi-instance/multi-printer support. Optionally print the saw name on labels (`DropLabel` / label payloads).

## Conflict avoidance (same job on both saws)

- Splitting a profile's cut list across saws is legitimate and the planner handles it. Optional: soft warning ("Saw 1 has an active <profile> stick for this job"), or hard lock per profile+job. Start with the warning.
- `recutEntry` (`increment qty_remaining`) and `stopStick` (cancel by `activeStickId`) need a station check so one tablet can't recut/cancel the other's work.
- Stale counts: Livewire fetches per interaction, so "remaining" can lag on the other tablet; add a light poll/refresh on the job list.
- Drop rack / `DropPlanner` are stock-wide: if both saws share one rack, two tablets could claim the same drop — needs a claim, or station-scoped sessions/drops.

## Rough size

Migrations/models: small. Station-scoped Dashboard + settings UI: the bulk (Dashboard is ~1450 lines, but changes concentrate in `mount`, bridge construction, and a handful of `StickSession::` queries). Manual mode: small–medium. Hardware/bridge side: depends on bridge repo. Tests: two-station scenarios (active-stick isolation, shared planner, recut/cancel scoping).

## Open questions

- Do both saws share one drop rack / stock pool, or are drops separate?
- On the manual saw, is the label the only output (operator sets the stop by hand)?
- Is the second printer on the same bridge host or elsewhere on the network?
