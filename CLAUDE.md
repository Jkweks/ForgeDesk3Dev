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

Roles and permissions are stored in the DB (`roles`, `permissions`, `role_permission` tables — created in `2026_02_09_000002`). Permissions are loaded at login and stored in `localStorage`. UI elements use `data-permission="permission.name"` attributes; a global JS function hides elements the user lacks permission for. Navigation items use `data-nav-permission="nav.section"` similarly.

The `Role` and `Permission` models handle the relationship. `User` has a `role` string field; permission checks resolve via `Role::where('name', $user->role)->first()->permissions`.

### Key Domain Models

- **Product** — core inventory item; has computed attributes (`quantity_available`, `suggested_order_qty`, `photo_url`, etc.) via `$appends`. Supports soft deletes.
- **InventoryLocation** — bin locations for a product's stock
- **JobReservation / JobReservationItem** — reserves inventory for a job; linked to BusinessJob
- **BusinessJob** — project-level job tracking
- **PurchaseOrder / PurchaseOrderItem** — procurement workflow (draft → submitted → approved → received)
- **CycleCountSession / CycleCountItem** — physical inventory counting workflow
- **FabricationDocument** — shop floor documents (PDF upload/storage)
- **DoorFrameConfiguration** and related models — door/frame product configurator with BOM generation
- **Machine / MachineTooling / MaintenanceTask / MaintenanceRecord** — equipment tracking

### PDF & Spreadsheet Generation

Reports use `barryvdh/laravel-dompdf` for PDF output and `phpoffice/phpspreadsheet` for Excel export. PDF report views live in `resources/views/pdfs/`.

### Testing

Tests use SQLite in-memory (configured in `phpunit.xml`). The test suite is sparse — mostly the Laravel scaffold examples. Feature tests go in `tests/Feature/`, unit tests in `tests/Unit/`.

### Docker / Deployment

`scripts/entrypoint.sh` runs on every container start: clears route/config/view caches, then runs `php artisan migrate --force` (unless `RUN_MIGRATIONS=false`). The `queue` service runs `php artisan queue:work` separately. Static assets are synced to a shared volume for nginx to serve.

`docker-compose.yml` is the development/production compose file (port 8040). `docker-compose.prod.yml` is an alternate production config.
