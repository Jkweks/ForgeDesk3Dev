# Customizable Dashboard

Branch: `feature/dashboard_ui` (from `develop` @ `22debd2`, which includes configv3/CutFlow).
Status as of 2026-10-06: **Phase 1 + work order / quality widgets done and committed; not yet browser-verified end to end; not merged or pushed.**

## Goal
`/` becomes a Tabler-demo-style dashboard where each user picks and arranges widgets over ForgeDesk data. Per-user layout plus an admin-set company default.

## Decisions
- GridStack 12.3.3 (vendored, `public/assets/gridstack/`) for drag + resize. Chart.js 4.5.1 vendored in `public/assets/chartjs/`.
- Old home page (Inventory Dashboard) moved to `/inventory/products` ("All Products" in the nav); `/` is the new dashboard. `/login` and `/password/reset` still render the `dashboard` view (login UI lives in `layouts/app`).
- Layout API is `/dashboard/layout` (not `/user/dashboard-prefs` as first planned) so one endpoint returns the resolved layout and the login payload stays small.
- Layout resolution: user's `users.dashboard_prefs` -> `company_settings.dashboard_default_layout` -> built-in (4 inventory stat cards). Widgets whose key is unknown or whose permission the user lacks are dropped on read and save.

## Architecture
- `app/Dashboard/WidgetRegistry.php`: the catalog. Every widget declares `permission`, `endpoint`, `type`, sizes, refresh interval. Types: `stat`, `list`, `table`, `chart`.
- `Api/DashboardLayoutController`: `GET /dashboard/widgets` (catalog filtered by permission), `GET|PUT|DELETE /dashboard/layout`, `PUT /dashboard/default-layout` (needs `settings.edit`).
- `Api/DashboardWidgetController`: cheap aggregate endpoints under `/dashboard/widgets/...`, each route behind `permission:` middleware.
- Frontend: `public/js/dashboard-widgets.js` (renderers per type, chart builders, edit mode) + `resources/views/dashboard.blade.php`.
- Migrations: `2026_10_06_000001` (users.dashboard_prefs), `2026_10_06_000002` (company_settings.dashboard_default_layout). Already run on the dev DB.

## Adding a widget (the recipe)
1. Add data endpoint (reuse an existing one if it is cheap) behind `permission:<same as registry>`.
2. Add entry in `WidgetRegistry::all()` group (`$stat`/`$list`/`$table`/`$chart` helpers).
3. Need user options? add `settings_schema` fields (select | multiselect | toggle, `show_if`) via the `$list`/`$table`/`$chart` helpers; the editor (gear in edit mode) renders them, values ride to the endpoint as query params, and the endpoint reads them with `WidgetRegistry::resolveSettings($key, $request->query())` (invalid values fall back to defaults; layout save runs `sanitizeSettings`).
4. New chart? add a builder to `chartBuilders` in the JS. New type? add a renderer.
5. Test the endpoint. `DashboardWidgetDataTest::test_every_widget_endpoint_is_gated_by_its_declared_permission` automatically checks every catalog entry returns 403 without its permission.

## Done
- Page split, registry, layout storage, admin default, edit mode (drag/resize/add/remove, widget settings; **autosaved** ~0.7s after each change with a Saving/Saved indicator, flushed on Done and on page leave; Reset; admin Save-as-default). An empty layout is a valid saved layout.
- Inventory (5 stat widgets), Work Orders (open / overdue / due this week / on hold stats, due-soon list, WIP-by-stage chart, work order table), Quality (pending / awaiting review stats, incident rate, problem types, weekly trend charts).
- Maintenance (overdue / due soon / active tasks / downtime stats reusing the gated `/maintenance/dashboard`, upcoming-tasks and recent-service lists) and Cycle Counting (active / in-progress / accuracy stats, session list) widgets, via lean gated endpoints (`/dashboard/widgets/maintenance/*`, `/dashboard/widgets/cycle-counts*`; the page-level `/cycle-counts-active|statistics` stay ungated and heavy, deliberately not used). Stat widgets accept a `suffix` (`%`, ` h`). Fixed `MaintenanceTask::getIsDueSoonAttribute` (Carbon 3 signed diff made every future task "due soon").
- Purchase orders (open / awaiting approval / overdue stats, due list; overdue = open and past `expected_date`), jobs and reservations (active / past-target stats, jobs-by-target list, open / overdue reservations; reservation terminal state is `fulfilled`), transactions (today count, recent list with signed change), lowest-stock list (plain columns, no Product appends), fabrication documents, configurator (non-archived counts, recent list), storage health, and CutFlow (guarded: returns `available:false` + nulls if the cutflow DB is unreachable).
- Per-widget settings framework (schema in the registry, gear editor, query-param transport, server-side sanitizing). The Work Order Table mirrors the user's saved Work Orders page columns: `App\Dashboard\WorkOrderColumns` reads `users.wo_column_prefs` (`{order, hidden}` or the legacy bare hidden list) on every load, so changes made on the page show up in the widget with no extra step; the widget can instead use its own column list (kept in the user's saved order). Labour-estimate columns load the heavy estimate relations only when shown.
- `/dashboard/stats` now requires `inventory.view` (was auth-only; nothing called it).
- Feature tests: `DashboardLayoutTest`, `DashboardWidgetDataTest` (15 passing).

## Remaining
1. **Browser verification** of the whole flow (drag/resize, save + reload, reset, dark mode, phone width, a role without WO/quality permissions). Not done by Claude: no browser session was driven.
2. Remaining domains: none planned. Not made into widgets on purpose: EZ Estimate stats (supplier catalog counts, not import activity) and the existing ungated `/transactions-*`, `/purchase-orders-open|statistics`, `/supplier-statistics` endpoints (widgets use new gated, lean endpoints instead).
3. More per-widget settings where useful (the framework is done; today: rows on every list, Work Order Table scope/columns/rows, incident-rate months, low-stock level).
4. Admin default-layout UX polish; consider a "default" indicator when a user is on the company default.
5. Known gaps: the Work Order Table widget has no column picker/filters/assignee/estimates (the page version loads heavy estimates; kept cheap). Several other stat endpoints in the app are still auth-only and un-gated (`/transactions-*`, `/purchase-orders-open|statistics`, `/cycle-counts-active|statistics`, `/supplier-statistics`, `/jobs`, `/ez-estimate/stats`): gate them before exposing as widgets.
6. Push / PR to `develop` when ready.

## Gotchas learned
- GridStack 12's default `renderCB` inserts `content` as plain text; the JS sets `GridStack.renderCB` to use innerHTML (content is built from escaped registry values).
- `grid.save()` omits default values (`x:0`, `y:0`); read `grid.engine.nodes` instead.
- `layouts/app.blade.php` `@yield('styles')` sits inside a `<style>` block; link vendored CSS from the view body.
- The container (`forgedesk-prod_app`, actually dev) keeps a cached `routes-v7.php`; run `route:clear` after adding routes or the new routes 404 (tests too). Test procedure: `config:clear`, `route:clear`, `php artisan test --filter ...`, then `config:cache`.
- The test DB contains ~112 quality reports seeded by a migration: assert deltas, not absolute counts.
- Checkout trouble on this host: `laravel/storage/fonts` and `storage/app/templates` are owned by uid 82; they were chowned to `docker` to allow branch switches.
