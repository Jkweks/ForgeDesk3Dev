# Absorb CutFlow into ForgeDesk

**Status: implemented and verified 2026-09-21.** Everything in this plan
landed as written — namespace layout, `cutflow` DB connection/tables,
`FdUser.fab_pin`-based auth with manual-only-when-signed-out, `/cut-station`
route, and the in-process `CutlistIngestService` replacing the HTTP call.
Verified live: PIN sign-in → job/profile selection → stick build → cut
record (via `Livewire::test()`), CSV upload, and a real
`DoorFrameConfigurationController::release()` call landing rows directly in
the new `cutflow` Postgres database with no network hop. All test data
cleaned up afterward. The standalone CutFlow deployment
(`cutflow-app-1`/`cutflow-db-1`) was left running, untouched, exactly as
planned — cutover is still a manual step for the user.

## Context

CutFlow (shop-floor cut-list import, stick-packing/planning against a
TigerStop, cut recording, Zebra label printing) currently runs as a fully
separate Laravel 13/Livewire 4 app + its own Postgres instance, talking to
ForgeDesk over HTTP (`CutFlowExportService` → CutFlow's
`POST /api/forgedesk/cutlist`, built earlier this session). The user has
decided to fold CutFlow's application code directly into the ForgeDesk
Laravel app instead of maintaining it as a second deployable — one app, one
deploy — while keeping CutFlow's own screens/visual design as-is and keeping
its data in a **separate Postgres database** on ForgeDesk's existing Postgres
server (a second Laravel DB connection, not shared tables).

Confirmed decisions:
- **UI**: add `livewire/livewire` to ForgeDesk and port the Dashboard/
  Settings Livewire components largely as-is (not a vanilla-JS rewrite).
- **Data**: start fresh in the new database — the live CutFlow instance's
  current data (3 cut jobs / 88 parts / 34 cut log entries / 1 operator) is
  abandoned, not migrated.
- **Auth**: the cut-station page stays reachable with no ForgeDesk login
  (tablet kiosk), matching the existing `/shop` route precedent
  (`routes/web.php` — bare `Route::get('/shop', fn () => view('shop-floor'))`,
  zero middleware). Operator identity switches from CutFlow's standalone
  `operators` table to **ForgeDesk's `FdUser.fab_pin`** — this field and its
  hash-check pattern already exist (`ShopFloorController::pinLogin`,
  `app/Models/FdUser.php`), so no new PIN field is needed anywhere. With no
  operator signed in, the dashboard is restricted to **manual entry only**
  (type a size, cut, print) — no job/cut-list browsing. This replaces the
  original "Cut as Unknown" bypass, which is being dropped: manual cuts stay
  available with nobody signed in, but real job cut-lists require a real PIN.

## Why in-process changes everything about the integration

Once CutFlow's code lives inside ForgeDesk, `CutFlowExportService` doesn't
need to make an HTTP call anymore — it can call a shared ingest method
directly. This retires: `CUTFLOW_BASE_URL`/`CUTFLOW_IMPORT_TOKEN` (ForgeDesk
`.env`), `FORGEDESK_IMPORT_TOKEN`/the `api/forgedesk/cutlist` route/the
`X-ForgeDesk-Token` check (CutFlow's standalone repo), and the CSRF exemption
added for that route. `WorkOrderController::uploadCutlist` and the
"Upload Cutlist" button stay, just calling the local service instead of
`Http::post()`.

The standalone CutFlow deployment (`/mnt/homeNAS/Container/cutflow`,
containers `cutflow-app-1`/`cutflow-db-1`) is **not touched or decommissioned
by this work** — it keeps running until the user has verified the absorbed
version and manually cuts the shop tablet over. tiger-bridge (the Node
service owning the serial/Zebra hardware) is untouched either way — it's
reached over HTTP from wherever `TIGER_BRIDGE_URL` points, and that just
moves from CutFlow's `.env` to ForgeDesk's.

## Namespace / file organization

Everything ported gets grouped under a `CutFlow` sub-namespace — this is an
unusually distinct bounded context (its own DB connection, its own kiosk
route, no interaction with ForgeDesk's permission system), so isolating it
avoids ever colliding with a future ForgeDesk model/controller of a common
name like `Part` or `Settings` (checked: no collisions today, but the
separate-DB nature makes this worth doing anyway):

- `app/Models/CutFlow/{CutJob,Part,StickSession,StickItem,CutLogEntry,CutFlowSetting}.php`
- `app/Livewire/CutFlow/{Dashboard,Settings}.php`
- `app/Services/CutFlow/{CutPlanner,TigerBridgeClient,CutlistIngestService}.php`
- `app/Http/Controllers/CutFlow/{ImportController,CutController}.php`
- `app/Support/Dimension.php` (no collision risk, can stay unnamespaced —
  confirmed nothing already named `Dimension` exists)
- `resources/views/cutflow/{layout.blade.php,livewire/dashboard.blade.php,livewire/settings.blade.php,import.blade.php,cuts-show.blade.php}`
- `resources/css/cutflow.css` (ported from CutFlow's `app.css` — confirmed
  it's plain CSS custom properties + one Google Fonts `@import`, no
  `@tailwind`/`@apply`, so it drops in as a static stylesheet unchanged)
- `database/migrations/cutflow/*.php` — fresh, consolidated migrations (not
  replaying CutFlow's 14-migration history) that create the final column
  shapes directly, each with `protected $connection = 'cutflow';` and loaded
  via `loadMigrationsFrom()` in `AppServiceProvider::boot()` (confirmed this
  provider and its `boot()` method already exist)

CutFlow's `Operator`/`AppSetting`/`User` models and their migrations are
**not ported** — `Operator` is replaced by `FdUser`, `User`/`operators`
table were unused/being-replaced scaffolding, `AppSetting` is renamed
`CutFlowSetting` to avoid the generic name colliding with intent later, same
`::current()` singleton pattern ForgeDesk already uses for
`CompanySetting`/`ConfiguratorSetting`.

## Database

- `docker exec forgedesk-prod_postgres psql -U forgedesk -c "CREATE DATABASE cutflow OWNER forgedesk;"`
  — one manual step against the live container (creating a new, separate,
  empty database; does not touch the `forgedesk` database or its data). The
  `forgedesk` Postgres role already owns it, so **no new credentials/role are
  needed** — just a different `DB_DATABASE` value on a second connection.
- `config/database.php`: add a `'cutflow'` connection cloned from the
  existing `pgsql` block, reading `CUTFLOW_DB_HOST`/`CUTFLOW_DB_PORT`/
  `CUTFLOW_DB_DATABASE`/`CUTFLOW_DB_USERNAME`/`CUTFLOW_DB_PASSWORD` — default
  host/port/user/password to the same values as the main `pgsql` connection
  (`env('DB_HOST')` etc.) so `.env` only strictly needs
  `CUTFLOW_DB_DATABASE=cutflow` added; only override the others if the user
  wants a dedicated role later.
- Every `CutFlow\*` model sets `protected $connection = 'cutflow';`.
- **Cross-database references stay plain integers, never real FKs**:
  `CutLogEntry.operator_id` (→ `FdUser.id`, default connection) and the
  work-order link on `CutJob` both drop their FK constraints — no
  `constrained()`/`foreignId()`, just `unsignedBigInteger(...)->nullable()`.
  `CutJob` keeps a work-order link (renamed conceptually from
  `forgedesk_job_id` string to a plain `work_order_id` integer — now that
  it's an in-process call there's no need for the `"wo-{id}"` string
  convention that existed only to survive a JSON API boundary; store
  `FdWorkOrder::id` directly). `CutLogEntry` keeps its `operator_name`
  snapshot column so history reads fine even if an `FdUser` is later renamed
  or deactivated.

## Auth / operator identity

- Drop `Operator::findByPin()` entirely. Add a small lookup (either inline in
  `Dashboard::confirmModal()`'s `login` branch, or a tiny
  `CutFlow\OperatorLookup` service) that mirrors
  `ShopFloorController::pinLogin()` exactly: loop `FdUser::where('active', true)->whereNotNull('fab_pin')`,
  `Hash::check($pin, $user->fab_pin)`. `FdUser` is on the **default**
  connection — reachable fine from the same request even though `Dashboard`
  itself works against `cutflow`-connection models; Eloquent handles mixed
  connections within one request natively.
- `is_admin` (gated the old Settings screen) becomes `$user->role === 'admin'`
  (`FdUser.role` is `worker|manager|admin` per its migration).
- Crew/session model stays as-is (session-array roster, multi-operator sign-
  in) — just backed by `FdUser` rows instead of `Operator` rows.
- **New restriction**: when `$crew` is empty (nobody has entered a valid
  PIN), the dashboard view hides job selection / parts pool / stick planning
  entirely and shows only the manual-entry keypad + a "Sign In" affordance.
  The old "Cut as Unknown" bypass button is removed — manual cuts remain
  available with no one signed in (their `CutLogEntry.operator_id` stays
  null, `operator_name` "Unknown", exactly like today's schema already
  supports), but browsing/cutting from real imported job data now requires a
  real signed-in `FdUser`.

## Route / page shape

Matches the existing `/shop` kiosk precedent (bare route, no middleware):

```php
Route::get('/cut-station', \App\Livewire\CutFlow\Dashboard::class);
Route::get('/cut-station/import', [\App\Http\Controllers\CutFlow\ImportController::class, 'show']);
Route::post('/cut-station/import', [\App\Http\Controllers\CutFlow\ImportController::class, 'store']);
Route::get('/cut-station/settings', \App\Livewire\CutFlow\Settings::class);
Route::get('/cut-station/cuts/{cutLogEntry:uuid}', [\App\Http\Controllers\CutFlow\CutController::class, 'show']);
```

`resources/views/cutflow/layout.blade.php` is a standalone HTML shell (not
extending ForgeDesk's Tabler-based `layouts/app.blade.php`) — same approach
`shop-floor.blade.php` already uses for its own kiosk page. Pulls in
`resources/css/cutflow.css` (added as a second Vite input alongside the
existing `resources/css/app.css`/`resources/js/app.js` in
`vite.config.js` — ForgeDesk already runs the same Tailwind 4 + Vite 7 stack
CutFlow does, so no new build tooling), plus `@livewireStyles`/
`@livewireScripts`.

## Services ported essentially unchanged

- **`CutPlanner`** (`unassignedPieces`/`buildStick`/`activeProfiles`/
  `projectRemainingStandardSticks`) — straight port, just repoint its model
  imports at `App\Models\CutFlow\*` and its settings source at
  `CutFlowSetting::current()`.
- **`TigerBridgeClient`** — straight port; reads
  `config('services.tiger_bridge.url'/'fake')`. Add that same config block
  to ForgeDesk's `config/services.php`, and add `TIGER_BRIDGE_URL=http://192.168.1.115:9111`
  / `TIGER_BRIDGE_FAKE=false` to ForgeDesk's `.env` (the real current values
  from CutFlow's `.env` — the shop tablet's tiger-bridge is already reachable
  from ForgeDesk's container the same way it's reachable from CutFlow's,
  since both are plain outbound HTTP to a LAN IP).
- **`Dimension`** support class — verbatim port, zero changes.
- **New `CutlistIngestService`** — the extracted `ingestRows()` logic from
  CutFlow's `ImportController` (CSV or array rows → upserted `CutJob`+`Part`
  rows, preserving already-cut `qty_remaining` on re-import by shifting it by
  the quantity delta rather than resetting it — this was a real bug fixed
  earlier this session, keep the fix). Called by both the ported
  `ImportController::store()` (CSV upload) and by
  `CutFlowExportService::exportWorkOrder()`/`sendRows()` directly (in-process
  method call replacing the old `Http::post()`).

## ForgeDesk-side integration rewrite

- `app/Services/Configurator/CutFlowExportService.php`: replace the
  `Http::post(...)` call in `sendRows()` with
  `app(\App\Services\CutFlow\CutlistIngestService::class)->ingest(...)`.
  `exportWorkOrder()`'s row-building logic (querying released
  `DoorFrameConfiguration`s, filtering lineal parts) is unchanged.
  `parseCsv()` stays for the manual "Upload Cutlist" path.
- Remove now-dead config: `config/services.php`'s `cutflow` block
  (`base_url`/`import_token`), `.env`'s `CUTFLOW_BASE_URL`/
  `CUTFLOW_IMPORT_TOKEN`.
- `DoorFrameConfigurationController::release()`'s call site and
  `WorkOrderController::uploadCutlist()` are otherwise unchanged — they
  already call `CutFlowExportService`, not CutFlow's HTTP endpoint directly.

## Verification

- `docker exec forgedesk-prod_postgres psql -U forgedesk -d cutflow -c '\dt'`
  after migrating — confirms the new tables landed in the right database,
  not `forgedesk`.
- `docker compose exec app php artisan tinker` — smoke-test
  `CutFlow\CutJob::create(...)`, confirm it's readable via
  `psql ... -d cutflow` and absent from `psql ... -d forgedesk`.
- Load `/cut-station` unauthenticated (no ForgeDesk session) — confirm it
  renders the manual-entry-only view with no job list.
- PIN-sign-in with a real `FdUser.fab_pin` — confirm job/parts data appears
  and a recorded cut lands in `cut_log_entries.cutflow` with the correct
  `operator_id`/`operator_name`.
- Release a configurator opening on a work order (existing tested flow from
  earlier this session) — confirm its cut-list lands in the new in-process
  `cutflow` database (via `CutlistIngestService`) with no network call
  involved, `qty_remaining` delta-preserving behavior still verified the same
  way it was tested against the standalone app.
- `TIGER_BRIDGE_FAKE` left `false` in ForgeDesk's `.env` only once someone
  confirms they're ready to test against real hardware from the new
  location — default it to `true` during initial build/testing to avoid
  accidentally moving the real TigerStop during development, then flip once
  verified (calling this out explicitly since the fake flag controls whether
  a bug in early testing could move real shop equipment).

## Addendum (2026-09-22): tiger-bridge source absorbed too

The original plan left tiger-bridge as-is, reached over HTTP wherever
`TIGER_BRIDGE_URL` points — correct for *runtime* (it still has to run on
whatever PC the TigerStop's serial cable is plugged into, which is not
ForgeDesk's host), but it meant tiger-bridge's source stayed behind in the
standalone `/mnt/homeNAS/Container/cutflow` tree, unversioned relative to
the app that now depends on it. Brought the source in:

- `tiger-bridge/` (root of this repo, alongside `laravel/`) — verbatim copy
  of `/mnt/homeNAS/Container/cutflow/tiger-bridge`'s `Dockerfile`,
  `.dockerignore`, `.env.example`, `package.json`, `server.js`, `README.md`.
  No code changes; README got one added paragraph clarifying it's absorbed
  but still deployed separately.
- `docker-compose.yml`: added an opt-in `bridge` service (profile
  `local-bridge`, matching CutFlow's own compose pattern) for the rare case
  dev/testing happens on a host with the serial device attached. Not started
  by default — `docker compose --profile local-bridge up -d`. **Not** added
  to `docker-compose.prod.yml`: prod runs on a remote VPS
  (`ghcr.io/jkweks/forgedesk3dev`) with no path to the shop's serial
  hardware, so a bridge service there could never work.
- `.gitignore`: added `tiger-bridge/node_modules`.
- No behavior change: `TIGER_BRIDGE_URL` still points at the shop tablet's
  LAN IP (`http://192.168.1.115:9111`) where tiger-bridge keeps running
  natively (`npm start`), same as before. To actually move that running
  instance onto the version now tracked here, copy this repo's `tiger-bridge/`
  directory over to the shop tablet (replacing the one still under
  `/mnt/homeNAS/Container/cutflow/tiger-bridge`) and restart it there — a
  manual cutover step, same pattern as the rest of this absorption, not done
  as part of this addendum.
- Found in passing, unrelated to this change: this repo's root
  `.env.example` currently has an **uncommitted** working-tree change that
  replaced its generic template contents with what look like real prod
  values (`DB_PASSWORD`, `APP_KEY`, `MAIL_PASSWORD`, etc.) — likely an
  accidental copy from a real `.env`. It already contains the
  `SERIAL_PORT`/`BAUD_RATE`/`PRINTER_HOST`/`PRINTER_PORT` bridge vars this
  addendum's compose service reads defaults from, so it's consistent with
  the change here, but it should not be committed as-is — it needs to go
  back to a scrubbed template before anyone commits it.
