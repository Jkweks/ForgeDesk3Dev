# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**ForgeDesk** is a manufacturing/fabrication ERP system built with Laravel 12. It manages inventory, purchase orders, job reservations, cycle counting, machine maintenance, fabrication documents, and a door/frame product configurator.

**Stack:** PHP 8.4 / Laravel 12, PostgreSQL 16, Redis, Nginx, Vite + Tailwind CSS 4, Tabler UI framework. Deployed via Docker Compose. Laravel Sanctum for API token auth.

## Common Commands

All commands run from `laravel/`:

```bash
# Run tests (uses in-memory SQLite — no DB required)
composer test
php artisan test --filter TestClassName

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Code style (Laravel Pint)
./vendor/bin/pint

# Local dev (runs server + queue + logs + vite concurrently)
composer dev

# Initial setup
composer setup

# Frontend only
npm run dev
npm run build

# Artisan helpers
php artisan migrate
php artisan route:list
php artisan tinker
```

Docker (from repo root):
```bash
docker compose up -d
docker compose exec app php artisan migrate
docker compose logs -f app
```

The app runs on port **8040** in Docker.

## Architecture

### Request Flow

All web routes (`routes/web.php`) serve Blade views — they are thin shells that return a single layout. **There is no server-side rendering of data.** All data loading happens via authenticated API calls from the frontend JavaScript embedded in each Blade view.

Authentication is handled entirely client-side: the login form in `dashboard.blade.php` posts to `POST /api/login`, stores the Sanctum token in `localStorage`, and attaches it as a `Bearer` header to every subsequent API call. The `/login` web route just returns `view('dashboard')`.

### API Layer

All API endpoints live under `routes/api.php`, prefixed with `/api/v1/` (after the `v1` prefix group). Most routes require `auth:sanctum` middleware. A few fulfillment routes are intentionally public (internal use).

**Route ordering matters:** Specific named routes (e.g. `/job-reservations/search-product`) must be declared before parameterized routes (`/job-reservations/{id}`) — this pattern is used throughout.

### Blade Views & Frontend JS

Each page (`resources/views/*.blade.php`) extends `layouts/app.blade.php` and contains its own `<script>` block with vanilla JS that calls the API. There is no frontend framework (React/Vue). Pages use the **Tabler** Bootstrap-based component library for UI.

`resources/js/app.js` is minimal (just imports bootstrap.js). Frontend assets are bundled by Vite.

### Permission System

Roles and permissions are stored in the DB (`roles`, `permissions`, `role_permission` tables). Permissions are loaded at login and stored in `localStorage`. UI elements use `data-permission="permission.name"` attributes; a global JS function hides elements the user lacks permission for. Navigation items use `data-nav-permission="nav.section"` similarly.

Frontend permission helpers available in every page's JS context: `hasPermission('inventory.edit')`, `canEdit('inventory')`, `canCreate('orders')`, `canDelete('users')`.

Backend permission checks use `$user->hasPermission('inventory.edit')` or `$user->hasAnyPermission([...])` — these query through the `Role` model relationship. **Always protect API endpoints server-side**; frontend hiding is UX only.

Common permissions: `inventory.view/create/edit/delete/adjust`, `orders.view/create/edit/delete`, `users.view/create/edit/delete`, `reports.view/export`, `pricing.view`, `maintenance.manage`, `settings.edit`. See `docs/ACTION_PERMISSIONS_GUIDE.md` for the full list.

### Inventory Quantity Management

**Source of truth:** `inventory_locations` table. `products.quantity_on_hand` and `products.quantity_committed` are denormalized totals derived from it.

**Canonical update path:** Modify `InventoryLocation.quantity`, then call `$product->recalculateQuantitiesFromLocations()`. This syncs product totals and calls `updateStatus()`.

`Product::adjustQuantity()` is **deprecated** — it edits `quantity_on_hand` directly without touching `inventory_locations`, causing drift. It still exists in `OrderController` and `MachineToolingController` (known tech debt). Prefer `InventoryLocationController::adjust()` for new code.

`Product` appends several computed fields to every JSON response via `$appends` (`quantity_available`, `suggested_order_qty`, `days_until_stockout`, `quantity_on_hand_packs`, `quantity_available_packs`, `counting_unit`, `pack_cost`, `photo_url`). Be aware these fire on every serialization.

### Key Domain Models

- **Product** — core inventory item. `quantity_available = quantity_on_hand - committed_from_reservations` (real-time from active reservations, not the `quantity_committed` column). Supports soft deletes. Products with `pack_size > 1` have separate pack/each UOM handling.
- **InventoryLocation** — bin locations for a product's stock; multiple per product
- **JobReservation / JobReservationItem** — reserves inventory for a job; linked to BusinessJob. Status values: `active`, `in_progress`, `on_hold`, `completed`, `cancelled`.
- **BusinessJob** — project-level job tracking
- **PurchaseOrder / PurchaseOrderItem** — procurement workflow (draft → submitted → approved → partially_received → received)
- **Category** — many-to-many with products (`category_product` pivot table, `is_primary` flag). `Product::category()` (singular) is deprecated; use `categories()`.
- **CycleCountSession / CycleCountItem** — physical inventory counting workflow
- **FabricationDocument** — shop floor documents (PDF upload/storage)
- **DoorFrameConfiguration** and related models — door/frame product configurator with BOM generation
- **Machine / MachineTooling / MaintenanceTask / MaintenanceRecord** — equipment tracking; tooling linked to products via `tool_type` field

### EZ Estimate Import

`EzEstimateController` processes Excel files uploaded from an external estimating system. Files are stored in `storage/app/ez_estimates/` and parsed with PhpSpreadsheet. This populates job reservations and material checks.

### PDF & Spreadsheet Generation

Reports use `barryvdh/laravel-dompdf` for PDF output and `phpoffice/phpspreadsheet` for Excel export. PDF report views live in `resources/views/pdfs/`.

### Testing

Tests use SQLite in-memory (configured in `phpunit.xml`). The test suite is sparse — mostly the Laravel scaffold examples. Feature tests go in `tests/Feature/`, unit tests in `tests/Unit/`.

### Docker / Deployment

`scripts/entrypoint.sh` runs on every container start: clears route/config/view caches, then runs `php artisan migrate --force` (unless `RUN_MIGRATIONS=false`). The `queue` service runs `php artisan queue:work` separately. Static assets are synced to a shared volume for nginx to serve.

`docker-compose.yml` is the development/production compose file (port 8040). `docker-compose.prod.yml` is an alternate production config.
