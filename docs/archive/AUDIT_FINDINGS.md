# ForgeDesk Codebase Audit — Inconsistencies & Workflow Issues

Audit date: 2026-08-03
Fixes applied: 2026-08-04
Follow-up fixes applied: 2026-08-16 (see "Status of remaining items" below)
Second follow-up applied: 2026-08-16 (see "Second follow-up" below) — closed every
remaining open item.

## Second follow-up (2026-08-16, same day)

- **`BusinessJobController::createTransaction` direct write — FIXED.** The no-location
  fallback now creates an "Unassigned" `InventoryLocation` (mirroring the
  `PurchaseOrderController`/`CycleCountItem` pattern) and always routes through
  `recalculateQuantitiesFromLocations()`.
- **Dead `inventory_commitments` view — FIXED.** Added a migration that drops it for good.
- **Zero test coverage — FIXED.** Added 15 passing Feature tests covering the exact
  drift-risk workflows fixed in this pass and the previous one: the `quantity_committed`
  clobber bug, PO receiving drift self-correction, the cycle-count legacy branch, the
  business-job transaction fallback, and permission-middleware gating. **Bigger finding
  along the way: the test suite could not run to completion on SQLite at all** —
  `database/migrations/2026_03_22_100000_recalculate_product_statuses.php` used
  Postgres-only `ALTER TABLE ... DROP CONSTRAINT` syntax,
  `2026_05_20_100003_restructure_fd_work_orders.php` dropped indexed columns without
  dropping the index first, and `2026_05_29_100001_increase_price_decimal_precision.php`
  altered `products` while the (now-dropped) `inventory_commitments` view still existed,
  which breaks SQLite's table-rebuild-on-column-change. All three are now
  driver-conditional and migrate cleanly on SQLite; `composer test` / `php artisan test`
  now actually runs end-to-end for the first time.
- **Response shape inconsistency — FIXED, via a different approach than a full rewrite.**
  Rather than touching ~120 individual `'error' => ...`-only response call sites across 20
  controllers (large, high-risk, no prior test coverage to catch mistakes), added
  `App\Http\Middleware\NormalizeApiErrorResponse` on the `api` middleware group: any JSON
  error response (status ≥ 400) gets both `message` and `error` keys mirrored if only one
  is present. This directly fixes the documented pain point (frontend defensively checking
  both) without touching controller business logic.
- **Dead `authToken`/`Bearer null` code — FIXED.** Removed the dead `let authToken = null`
  declaration and every `Authorization: Bearer ${authToken}` / `'Bearer ' + authToken`
  header across all 14 blade views that had them (more than the ~9 originally estimated) —
  `admin.blade.php`, `reports.blade.php`, `critical-stock.blade.php`, `low-stock.blade.php`,
  `cycle-counting.blade.php`, `jobs.blade.php`, `storage-locations.blade.php`,
  `fulfillment/material-check.blade.php`, `fulfillment/job-reservations.blade.php`,
  `partials/product-modal.blade.php`, `fabrication/documents.blade.php`,
  `fabrication/work-orders.blade.php`, `admin/location-assignment.blade.php`. Safe because
  `API_BASE` is same-origin (`/api/v1`) and fetch's default `same-origin` credentials mode
  already sends the session cookie; Sanctum ignores the bearer header entirely once a
  request is recognized as stateful.
- **New issue found and fixed while doing this pass — not in the original audit:**
  extending `permission:` middleware to `products`/`categories`/`suppliers`/
  `storage-locations` (task above) would have silently locked `manager`/`fabricator`/
  `viewer` out of CRUD they're documented to have — the base `inventory.*`/`orders.*`
  permissions created in the original 2026-02-09 seed migration were **only ever assigned
  to `admin`**; every later migration added narrower permissions (`orders.submit`,
  `cycle-count.*`, `jobs.*`, ...) and assigned those correctly, but nobody closed the gap
  for the base CRUD permissions `ACTION_PERMISSIONS_GUIDE.md` documents for Warehouse
  Manager / Receiving Clerk / Production Viewer. Added
  `2026_08_16_000002_grant_base_crud_permissions_to_manager_and_others.php` to grant
  exactly what that guide already documents. Caught by writing the permission-middleware
  regression test, not by static review — worth noting as a case for why the test-coverage
  item mattered.

## Status of remaining items (2026-08-16 follow-up)

- **Broader `apiResource` permission gaps — FIXED.** Added `permission:` middleware
  (via `->middlewareFor()`) to every previously-ungated `apiResource` route:
  `categories`/`suppliers`/`products`/`storage-locations` (`inventory.view/create/edit/delete`),
  `purchase-orders`/`orders` (`orders.view/create/edit/delete`, plus `orders.submit`/
  `orders.approve`/`orders.receive` on their action endpoints — previously `approve` only
  required `orders.edit`, now uses the dedicated `orders.approve` permission that already
  existed in the permissions table but wasn't wired up), `cycle-counts`
  (`cycle-count.view/create/record/approve/complete/cancel/export`), `machines`/`assets`/
  `maintenance-tasks`/`maintenance-records` (`maintenance.view`/`maintenance.manage`), and
  `business-jobs` (`jobs.view/create/edit/delete/manage-reservations/manage-transactions`).
- **Inventory `quantity_committed` drift — FIXED, and found a worse bug than originally
  documented.** `OrderController::commitInventory`/`releaseInventory`/`shipOrder` wrote
  `quantity_committed` directly, as documented — but investigating the "later recalc wipes
  it" concern turned up that `JobReservationItem`'s model-event hook
  (`syncProductCommittedQuantity()`) already *unconditionally overwrites*
  `product.quantity_committed` with only the job-reservation total on every reservation
  item save/delete, silently erasing any sales-order commitment on the same product. Fixed
  by adding `Product::recalculateCommittedQuantity()` (sums `JobReservationItem::binAwareCommitted()`
  + `CommittedInventory` order commitments) and routing both `JobReservationItem` and
  `OrderController` through it, so neither system can clobber the other's commitment.
- **`PurchaseOrderController::receive` drift — FIXED.** Now updates `InventoryLocation`
  first, then calls `recalculateQuantitiesFromLocations()` instead of incrementing
  `quantity_on_hand` directly — self-corrects any pre-existing drift instead of compounding it.
- **`CycleCountItem` legacy direct-write branch — FIXED.** The no-`location_id` legacy path
  now applies the variance to the primary inventory location (creating an "Unassigned"
  location if none exists) and calls `recalculateQuantitiesFromLocations()`, matching the
  pattern used elsewhere, instead of writing `quantity_on_hand` directly.
- **Deprecated `category()` in `ReportsController` — FIXED.** All 6 flagged call sites now
  use `primaryCategory()`. Also switched the corresponding eager loads from the deprecated
  `'category'` relation to `'categories'`, and made `Product::primaryCategory()` relation-aware
  (uses the loaded `categories` collection when present) to avoid introducing an N+1 query
  regression across these report endpoints.
- **Duplicate fabrication-documents route — FIXED.** Removed the redundant `POST` registration;
  confirmed the frontend's multipart edit calls (`fabApiMultipart(..., fd)` with `_method=PUT`
  in the body) are already routed correctly by the single `PUT` registration, since Laravel's
  `Request::capture()` enables HTTP method-parameter override globally.
- **Not yet touched:** the dead `authToken`/`Bearer null` headers (still low severity, same
  as original pass), the response-shape inconsistency (`{message}` vs `{error}` vs `{success}`),
  the dead `inventory_commitments` DB view, and zero test coverage for these workflows.

## Status of critical items

- **Authorization gap — FIXED (partially).** Added a `permission` route middleware
  (`app/Http/Middleware/CheckPermission.php`, aliased in `bootstrap/app.php`) and applied
  it to the highest-risk endpoints: all of `UserController` (`users.view/create/edit/delete`),
  all of `RoleController` (`roles.view/create/edit/delete`), `ProductController::adjustInventory`
  and `InventoryLocationController::adjust` (`inventory.adjust`), and
  `PurchaseOrderController::approve` (`orders.edit`). ~~**Not yet fixed:** the broader CRUD
  `apiResource` routes (`products`, `purchase-orders`, `suppliers`, `categories`, etc.) still
  have no server-side permission checks — only the endpoints named in the original audit as
  most severe (privilege escalation, inventory/financial adjustment) were addressed. Extending
  `permission:` middleware across the rest of `routes/api.php` is a reasonable follow-up.~~
  (Closed in the 2026-08-16 follow-up — see below.)
- **Login/session/CSRF root cause — FIXED.** `laravel/.env` had `APP_URL=http://localhost:8041`
  (wrong port, and not the actual access method); corrected to `APP_URL=https://dev.kweks.co`
  and added an explicit `SANCTUM_STATEFUL_DOMAINS=dev.kweks.co,fab.kweks.co` so the app no
  longer depends on the fragile computed fallback (which was silently wrong) or solely on the
  docker-compose environment override from the separate repo-root `.env`.
- **Missing `session()->regenerate()` on login — FIXED.** Added to the login handler in
  `routes/api.php`.
- **Inconsistent CSRF token source — FIXED.** `apiCall`/`authenticatedFetch` and the logout
  handler in `auth-scripts.blade.php` now all read the live `XSRF-TOKEN` cookie via a shared
  `getXsrfToken()` helper (same approach the login form already used), instead of a stale
  `<meta name="csrf-token">` value stamped once at page load.
- **HTTP 419 (CSRF mismatch) — FIXED.** `authenticatedFetch` now retries once with a freshly
  fetched CSRF cookie on a 419, and surfaces a clear notification instead of a raw error if
  the retry also fails.
- **Not yet touched:** the dead `authToken = null` variable and the ~9 blade views still
  sending `Authorization: Bearer null` (low severity — harmless today, flagged in the original
  report as a future drift risk, not part of this pass).

## Critical — Authorization

~~Almost no backend permission enforcement exists. CLAUDE.md documents a frontend/backend
split (`data-permission` is UX-only, backend must independently check `hasPermission()`),
but grepping every `app/Http/Controllers/Api/*.php` file turns up essentially zero
`hasPermission`/`hasAnyPermission` calls, and `bootstrap/app.php:15-20` registers no
permission middleware at all.~~

- ~~`UserController.php` (`store` L111, `update` L142, `destroy` L184, `restore` L205,
  `resetPassword` L219) — any authenticated user can create/delete users and reset
  anyone's password.~~
- ~~`RoleController.php` (`store` L64, `update` L95, `destroy` L127, `assignPermissions`
  L181) — any authenticated user can create roles and assign arbitrary permissions, i.e.
  self-escalate to admin.~~
- ~~Also unguarded: `PurchaseOrderController::approve`, `ProductController::adjustInventory`,
  `InventoryLocationController::adjust`.~~

~~This is the single biggest issue — worth fixing before anything else here.~~

## High — Inventory accounting has multiple disconnected write paths

Beyond the known `Product::adjustQuantity()` tech debt (already documented in CLAUDE.md,
used in `OrderController` and `MachineToolingController`), several more places bypass the
canonical `InventoryLocation` + `recalculateQuantitiesFromLocations()` path:

- ~~`OrderController.php:158-159, 180-182` — `commitInventory`/`releaseInventory` mutate
  `quantity_committed` directly, never touching `inventory_locations`. A later
  location-based recalc will silently wipe these commitments.~~
- ~~`PurchaseOrderController.php:296-347` (`receive`) — bumps `quantity_on_hand` directly
  *and* updates the `InventoryLocation` row separately, but never reconciles via
  recalculate — any pre-existing drift compounds instead of correcting.~~
- ~~`BusinessJobController.php:779-789` — falls back to a direct `quantity_on_hand` write
  when no location row exists for the product.~~
- ~~`CycleCountItem.php:251-253` — a "legacy" branch (author's own comment: "should not
  happen going forward") also writes `quantity_on_hand` directly.~~

Net effect: `quantity_on_hand`/`quantity_committed` can drift from the sum of
`inventory_locations` through at least four independent code paths, not just the two
documented ones.

## Medium

- ~~**Deprecated `category()` still used in reporting** — `ReportsController.php:172, 281,
  333, 517, 649, 1048` all use the deprecated singular `$product->category` instead of
  `categories()`/`primaryCategory()`. Any product categorized only via the pivot table
  shows as "Uncategorized" in these reports — an actual accuracy bug, not just style debt.~~
- ~~**Dead/inconsistent DB view** — migration
  `2026_01_24_000002_restructure_job_reservations_for_fulfillment.php:56-83` creates
  `inventory_commitments`, a raw SQL view that recomputes commitments ignoring bins —
  diverging from the current bin-aware `JobReservationItem::binAwareCommitted()` logic.
  Nothing in the app queries it; it's dead, and would return wrong numbers if anyone did.~~
- ~~**Zero test coverage for the risky workflows** — the only test files are the default
  Laravel scaffolds (`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`).
  Inventory adjustment, job reservations, PO receiving, and cycle counts — exactly the
  areas with the drift risk above — have no regression protection at all.~~

## Low

- ~~**Response shape inconsistency** — most controllers return `{message: ...}`, 17 also
  add `{error: ...}` on exceptions (e.g. `PurchaseOrderController.php:162-163, 398-399`),
  while `EzEstimateController` uses a `{success: bool}` convention instead. The frontend
  already has to defensively check both `error.error` and `error.message` to cope
  (`auth-scripts.blade.php:381-388`).~~
- ~~**Dead auth-token code** — `auth-scripts.blade.php:3` declares `authToken = null`,
  never set anywhere, yet 9 blade views still send `Authorization: Bearer null` —
  harmless today only because Sanctum's session cookie carries the real auth. Risk: if
  someone "fixes" the null token later without checking, they could break the
  cookie-based path that's actually load-bearing.~~
- ~~**Duplicate route registration** — `routes/api.php:443-444` registers
  `fabrication-documents/{fabricationDocument}` update as both POST (with a
  `_method=PUT` override) and PUT — redundant, and could drift if one copy is edited
  without the other.~~

## Critical — Login / session / "remember me" / CSRF

~~**Root cause of the CSRF mismatches and unreliable "remember me":** `.env:5` sets
`APP_URL=http://localhost:8041`, but `docker-compose.yml:70` actually publishes nginx on
port **8040** (a one-digit typo). Sanctum's stateful domain list
(`config/sanctum.php:18-23`) is built from `Sanctum::currentApplicationUrlWithPort()`,
which derives its host:port from `APP_URL` — so it only ever registers
`localhost:8041` (wrong port, nobody actually uses it) plus the generic
`127.0.0.1:8000` default. The real access URL, `localhost:8040` (and any LAN
IP/hostname:8040 users actually hit in a Docker deployment), is never in the list.
`SANCTUM_STATEFUL_DOMAINS` is passed through in `docker-compose.yml:56` but not set by
default, so nothing overrides the broken computed list.~~

~~Effect: when a request's Origin/host isn't in the stateful domain list,
`EnsureFrontendRequestsAreStateful` (`bootstrap/app.php:18`) treats it as a stateless API
request instead of a first-party SPA request. Sanctum then expects bearer-token auth —
but (per the earlier finding) `authToken` is always `null` and never actually set — so
the session/CSRF cookies that *were* issued are effectively ignored for auth purposes.
This produces exactly the symptom reported: CSRF mismatches and a "remember me" checkbox
that appears to do nothing, because the browser is frequently talking to the app over a
host:port combination Sanctum doesn't recognize as stateful.~~

Contributing/secondary issues found in the same flow:

- ~~**Remember-me wiring itself is correct** — `#loginRemember` checkbox value is read and
  sent as `remember` in the login POST body (`auth-scripts.blade.php:410, 425`), and the
  backend honors it via `Auth::login($user, $remember)` with
  `$remember = $request->boolean('remember', false)`
  (`routes/api.php:63-64`). It only fails to have a lasting effect because of the
  stateful-domain problem above.~~
- ~~**No session regeneration on login** — `routes/api.php:44-84` never calls
  `$request->session()->regenerate()` after `Auth::login()`. This is a session-fixation
  risk and also leaves the pre-login CSRF token valid post-auth. Logout
  (`routes/api.php:86-91`) does correctly call `session()->invalidate()` and
  `regenerateToken()`, so login and logout are inconsistent with each other here.~~
- ~~**Inconsistent CSRF token source** — login (`auth-scripts.blade.php:414`) fetches a
  fresh token via `/sanctum/csrf-cookie` right before submitting, but logout
  (`auth-scripts.blade.php:449-456`) and the general-purpose `apiCall`/`authenticatedFetch`
  helper (`auth-scripts.blade.php:348, 362-392`) instead read a `<meta name="csrf-token">`
  tag that was stamped at page load and can go stale on long-lived tabs — a second,
  independent source of intermittent CSRF mismatches on top of the domain issue.~~
- ~~**HTTP 419 (CSRF mismatch) is never specifically handled** — `authenticatedFetch`
  (`auth-scripts.blade.php:362-392`) only special-cases 401 (367-373); a 419 falls through
  to a generic thrown error with no retry-with-fresh-token and no redirect to `/login`, so
  users just see a raw error instead of a recoverable prompt.~~

~~**Most impactful fix, in order:** correct `APP_URL` (or explicitly set
`SANCTUM_STATEFUL_DOMAINS` to cover every host:port combination users actually use, e.g.
LAN IP and hostname on `:8040`) — this alone likely resolves the majority of the reported
symptoms. The stale-meta-tag CSRF source and missing 419 handling are worth fixing
alongside it since they'd otherwise keep causing occasional, harder-to-reproduce
mismatches even after the domain fix.~~

## Note — route ordering is actually fine

The `{id}` vs. specific-route shadowing pattern CLAUDE.md warns about (specific named
routes must precede parameterized routes) is correctly handled everywhere checked in
`routes/api.php` — `job-reservations` even has an explicit comment calling this out, and
other resources avoid the trap entirely by using hyphenated top-level paths
(`/cycle-counts-active`, `/purchase-orders-statistics`, etc.) instead of nested
`{id}/action` paths that would collide.
