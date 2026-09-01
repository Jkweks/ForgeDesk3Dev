# ForgeDesk Feature Suggestions

**Created:** 2026-09-01  
**Note:** SSO (Microsoft/Azure) and the Configurator Integration each have their own dedicated plan docs. This file covers all other proposed features.

---

## Feature Index

| Feature | Status | Effort |
|---|---|---|
| [Shop Floor War Room Dashboard](#1-shop-floor-war-room-dashboard) | Next phase (this week) | ~2–3 days |
| [PM Dashboard](#2-pm-dashboard) | Planned | ~2 days |
| [Smart Purchasing — Auto-Draft POs](#3-smart-purchasing--auto-draft-pos) | Under management review | ~2 days |
| [QR Code Labels](#4-qr-code-labels) | Under consideration | ~2 days |
| [Job Schedule Board](#5-job-schedule-board) | Backlog | ~2 days |

---

## 1. Shop Floor War Room Dashboard

**Status:** Next phase (flagged for this week)

### What It Is
A big-screen, auto-refreshing production dashboard designed to run on a TV or monitor on the shop floor — no login required, using the same unauthenticated kiosk pattern already established.

### Why It's Valuable
All the data it needs is already being written to the database and never surfaced:
- `FdStageLog` records every stage transition with user + timestamp
- `FdWoStage` has `started_at` and `completed_at`
- `FdWoElevation` has `date_requested` and `date_completed`

### What It Shows
- **Active work orders** — each elevation's current stage, who's working it, how long it's been there. Anything sitting in a stage longer than the historical average glows amber/red automatically.
- **Today's throughput** — completions vs a daily goal, live-updating.
- **Bottleneck callout** — which stage template has the longest average dwell time this week (visible stack-up signal for supervisors).
- **Worker board** — stages completed per fab user today.
- **Machine status** — any machine in a current `MaintenanceRecord` shows as DOWN with reason.

### Technical Approach
- New unauthenticated web route (e.g. `GET /shop/dashboard`) returning a full-screen Blade view
- Same pattern as existing `ShopFloorController` unauthenticated routes
- New API endpoints aggregating `fd_wo_stages`, `fd_wo_elevations`, `fd_stage_log` data
- Auto-refreshes every 30 seconds via `setInterval` fetch
- No new data collection needed — purely surfaces existing data

### Key Files to Reference
- `app/Http/Controllers/Api/ShopFloorController.php` — existing kiosk endpoints (unauthenticated pattern)
- `app/Models/FdWoStage.php` — has `started_at`, `completed_at`, `assigned_to_id`
- `app/Models/FdWoElevation.php` — has `date_requested`, `date_completed`
- `app/Models/FdStageLog.php` — write-only currently; this feature is the first reader
- `app/Models/Machine.php` + `app/Models/MaintenanceRecord.php` — for machine status

### Estimated Effort
~2–3 days: 3–4 new aggregation API endpoints + one new full-screen Blade view.

---

## 2. PM Dashboard

**Status:** Planned — plan written, not yet implemented

### What It Is
A dedicated internal view for project managers. Shows all jobs assigned to a PM with at-a-glance health indicators, key dates, and quick navigation into job details.

### Context
`BusinessJob.project_manager` is a free-text `VARCHAR` field (not a FK to users). Added in migration `2026_05_20_100000_add_pm_to_business_jobs.php`. No filtering by PM currently exists on any endpoint.

### What It Shows
- PM dropdown (populated from distinct `project_manager` values in active jobs) — defaults to logged-in user's `full_name` if it matches any PM string
- Job cards showing: job number, job name, customer, status badge, health pill (On Track / At Risk / Blocked), days remaining / days overdue, work order progress bar (completed / total), unfulfilled reservation items count
- Health scoring (computed server-side):
  - `blocked` — past `target_completion_date` with status still `active`, OR reservation items with `committed_qty = 0`
  - `at_risk` — `days_remaining ≤ 14` with open unfulfilled reservation items, OR work orders with no completed stages
  - `on_track` — everything else active
- Sort: blocked first, then by days remaining
- Auto-refreshes every 60 seconds
- PM selection persists in `localStorage`

### Technical Approach
- New API endpoint: `GET /api/v1/business-jobs/dashboard` (with optional `?pm=` filter) — eager-loads `jobReservations.items` and `workOrders.elevations.stages`
- New API endpoint: `GET /api/v1/business-jobs/pm-list` — distinct PM name strings
- **Route placement:** Declare both before the `apiResource` `{id}` block in `routes/api.php` to avoid param collision
- New web route: `GET /pm-dashboard`
- New Blade view: `resources/views/pm-dashboard.blade.php`
- New nav link in `layouts/app.blade.php`, gated on `jobs.view` permission

### Key Files to Reference
- `app/Models/BusinessJob.php` — `project_manager` field, `jobReservations()` and `workOrders()` relationships
- `app/Http/Controllers/Api/BusinessJobController.php` — existing `index` method (model for new endpoints)
- `routes/api.php` lines ~411–430 — existing business-jobs routes (new routes go before `apiResource`)
- `resources/views/jobs.blade.php` — existing jobs view (reference for card/layout patterns)

### Estimated Effort
~2 days: 2 new API endpoints + new Blade view + nav link.

---

## 3. Smart Purchasing — Auto-Draft POs

**Status:** Under management review — do not implement until cleared

### What It Is
A "Generate Purchasing Plan" action that scans inventory, identifies items needing reorder, groups them by supplier, and creates draft purchase orders pre-populated and ready for review.

### Why It's Valuable
The system already computes `days_until_stockout` on every `Product` (via `$appends`), has supplier relationships, and has a full PO creation flow. A reorder recommendations report exists but is read-only — users must manually cross-reference it and build POs by hand.

### What It Does
1. Finds every product where `days_until_stockout` is below threshold (or quantity under reorder point) with no open PO already covering it
2. Groups those products by their primary supplier
3. Creates draft `PurchaseOrder` records, pre-populated with recommended quantities — one per supplier
4. Returns a summary: "4 draft POs created covering 17 items across 3 suppliers"

### Optional Enhancement
A weekly scheduled email digest: "Your purchasing plan for the week: 3 items trending toward stockout before next expected delivery." Uses Laravel's existing queue system.

### Technical Approach
- New API endpoint: `POST /api/v1/purchase-orders/generate-plan`
- Logic: query products with `quantity_on_hand <= reorder_point` (or `days_until_stockout < X`) AND no open PO items covering them → group by primary supplier → call existing PO creation logic
- New button on the inventory/purchasing view: "Generate Purchasing Plan"

### Key Files to Reference
- `app/Models/Product.php` — `$appends` including `days_until_stockout`, `suggested_order_qty`
- `app/Models/PurchaseOrder.php` + `PurchaseOrderItem.php`
- `app/Http/Controllers/Api/PurchaseOrderController.php` — existing creation flow to reuse
- `app/Models/Supplier.php` — supplier relationships

### Estimated Effort
~2 days.

---

## 4. QR Code Labels

**Status:** Under consideration — user expressed interest

### What It Is
Every product, storage bin, machine, and work order gets a printable QR code label. Scan with any phone or tablet and land directly on that record in ForgeDesk — ready to adjust inventory, log maintenance, or open a work order.

### Why It's Cool
- App already runs on shop floor tablets (kiosk pattern established)
- Everything has a URL already
- Browser camera APIs handle scanning natively — no native app needed
- `barryvdh/laravel-dompdf` already installed for label PDF generation

### What You Get
- **Products** — scan bin label → product page → adjust quantity, view location, check stock. Cycle counting becomes scan-and-enter.
- **Storage locations** — scan shelf label → see everything in that bin with quantities.
- **Machines** — scan machine tag → log maintenance record or view service history.
- **Work order elevations** — scan elevation tag on shop floor → open that elevation's stage view directly.

### Technical Approach
- **QR generation:** One composer package (e.g. `endroid/qr-code`) — generates QR from a signed URL
- **Label PDF:** New "Print Label" endpoint per record type using `dompdf` — renders small label with QR code, item name, key info (SKU, location, etc.)
- **Scan page:** Lightweight `GET /scan` Blade view using browser `BarcodeDetector` API (Chromium-supported, same browser as kiosk). Scans → reads URL from QR → `window.location.href = url`
- **Signed URLs:** Use `URL::signedRoute()` to prevent guessable QR codes for sensitive records

### Key Files to Reference
- `app/Http/Controllers/Api/ShopFloorController.php` — kiosk pattern reference
- `routes/web.php` — where to add the `/scan` route and label routes
- `resources/views/partials/auth-scripts.blade.php` — for understanding session/auth flow post-scan

### Estimated Effort
~2 days: QR generation, 3–4 label PDF endpoints, scan-redirect page.

---

## 5. Job Schedule Board

**Status:** Backlog

### What It Is
A Gantt-style timeline showing every active job as a horizontal bar plotted against target completion dates, color-coded by health. Gives management an immediate read on the full job pipeline without opening individual records.

### What It Shows
- All active `BusinessJob` records as bars on a scrollable timeline
- Color coding: green (on track), amber (within a week of deadline with open work), red (past target date or missing materials)
- A "today" line for instant visual reference
- Click a bar → expand inline to show open reservations, unfulfilled items, linked work orders

### Technical Approach
- Pure CSS grid timeline — no charting library
- New API endpoint aggregating `BusinessJob`, `JobReservation`, and `FdWorkOrder` data
- New Blade view and web route

### Key Files to Reference
- `app/Models/BusinessJob.php` — `start_date`, `target_completion_date`, `status`
- `app/Models/JobReservation.php` + `JobReservationItem.php` — fulfillment status
- `app/Models/FdWorkOrder.php` — `isComplete()` method

### Estimated Effort
~2 days.

---

## Implementation Notes (All Features)

### Common Patterns to Follow
- All new API endpoints go in `routes/api.php` inside the `auth:sanctum` middleware group with appropriate `permission` middleware
- All new web routes go in `routes/web.php` returning `view('...')`
- All new Blade views extend `layouts/app.blade.php`
- New nav links go in `layouts/app.blade.php` with `data-nav-permission` gating
- Backend permission checks use `$user->hasPermission()` — frontend hiding is UX only
- Run `./vendor/bin/pint` after any PHP changes
- Run `composer test` to verify no regressions

### Existing Permissions That Cover These Features
- `jobs.view` — PM Dashboard, Job Schedule Board
- `orders.view` — Smart Purchasing (read)
- `orders.create` — Smart Purchasing (draft PO creation)
- `maintenance.manage` — QR label scan for machines
- `inventory.view` — QR label scan for products
- `fabrication.work-orders.view` — Shop Floor Dashboard, QR for work orders
