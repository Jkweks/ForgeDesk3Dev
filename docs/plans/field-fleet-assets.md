# Field & Fleet Assets

Status: BUILT (ForgeDesk side) 2026-10-25, phases 1-4. Not yet built: service-history PDF, cost report/Excel, QR label PDF, email digests, dashboard widgets (phase 5). The field app itself is a separate project; its contract is docs/fleet-field-sync-contract.md.

## Goal

A new section for assets that live outside the shop: vehicles, trailers, large field equipment, swing stages, gang boxes, lifts, generators, and similar. Each asset gets robust maintenance tracking (schedules, work history, costs, downtime), compliance and inspection tracking, and document storage. It is **fully separate** from the fabrication/shop maintenance module.

## Separation from existing code (hard rules)

The shop module owns `machines`, `machine_types`, `maintenance_tasks`, `maintenance_records`, `machine_tooling`, and the legacy `assets` / `asset_machine` tables. `maintenance_tasks.machine_id` and `maintenance_records.machine_id` are NOT NULL FKs to `machines`, so field assets cannot be wedged in without distorting the shop schema.

- Do not reuse or modify any of those tables, models, controllers, routes, `maintenance.*` permissions, or `maintenance.blade.php`.
- The legacy `Asset` model and `assets` table already exist (shop-side). Avoid the name. New tables use the `field_asset*` prefix and new models are `FieldAsset*`. New permissions use the `fleet.*` namespace.
- Shared code is limited to generic infrastructure: the permission system, the `Storage` pattern from `JobDocumentController`, `SystemNotification`, scheduling, and Tabler layout.

## Data model

All new tables, one migration per table, dated from 2026_10_24.

**field_asset_types** (lookup, admin-editable, seeded): `name`, `category` (vehicle | trailer | heavy_equipment | access_equipment | storage | power_tools | other), `meter_type` (odometer_miles | engine_hours | none), `default_checklist` json (optional), `is_active`. Seeds: Truck, Van, Trailer, Forklift/Lift, Swing Stage, Gang Box, Generator, Compressor, Scaffold/Rigging, Other.

**field_assets**: `asset_number` (unique, human tag e.g. "FA-0012"), `name`, `field_asset_type_id`, `status` (active | in_service_job | out_of_service | in_repair | sold | retired), `make`, `model`, `year`, `serial_number`, `vin` (nullable), `license_plate` + `plate_state` (nullable), `purchase_date`, `purchase_cost`, `vendor`, `warranty_expires_on`, `current_meter` + `meter_type` + `meter_updated_at`, `assigned_user_id` (driver/custodian, nullable), `home_location_id` (FK to `company_locations`), `current_location_text` (free text, e.g. "Job 24-118 Main St"), `current_business_job_id` (nullable FK to `business_jobs`), `parent_asset_id` (nullable self-FK, e.g. gang box on a trailer, tools in a gang box), `photo_path`, `notes`, soft deletes. Indexes on status, type, assigned user, job.

**field_asset_meter_readings**: `field_asset_id`, `reading`, `read_at`, `recorded_by`, `source` (manual | work_order | inspection). Append-only. Updating `current_meter` happens in one service method, which rejects a reading lower than the latest unless flagged as a correction.

**field_asset_maint_plans** (recurring schedules): `field_asset_id` (or `field_asset_type_id` for type-level templates that are applied per asset), `title`, `description`, `interval_days` nullable, `interval_meter` nullable (both may be set: whichever comes first), `warn_days` / `warn_meter` lead times, `last_done_at`, `last_done_meter`, computed `next_due_at` / `next_due_meter` (stored, recalculated on completion), `priority`, `default_vendor`, `estimated_cost`, `is_active`. Pattern follows `maintenance_tasks`, but supports meter-based intervals, which the shop module lacks.

**field_asset_work_orders** (the maintenance history record, and also unplanned repairs): `wo_number`, `field_asset_id`, `plan_id` nullable, `type` (preventive | repair | inspection_defect | recall | other), `status` (open | scheduled | in_progress | waiting_parts | completed | cancelled), `priority`, `title`, `description`, `reported_by`, `reported_on`, `scheduled_for`, `completed_on`, `meter_at_service`, `performed_by` (internal user or vendor), `vendor`, `labor_hours`, `labor_cost`, `parts_cost`, `outside_cost`, `total_cost` (computed), `downtime_days`, `invoice_number`, `notes`. Completing a work order that has a `plan_id` advances that plan and writes a meter reading. Line items live in **field_asset_wo_items** (`description`, `qty`, `unit_cost`, optional `product_id` link to inventory, with no automatic stock deduction in v1).

**field_asset_inspections** + **field_asset_inspection_items**: checklist-based inspections (pre-trip, swing stage pre-use, monthly gang box, annual). Header holds `field_asset_id`, `template_name`, `performed_by`, `performed_at`, `meter`, `result` (pass | pass_with_issues | fail), and `notes`. Items hold `label`, `result` (pass | fail | n/a), and `comment`. A failed item can spawn a work order of type `inspection_defect` with one click. A fail result on a safety-critical type auto-sets the asset to `out_of_service` (confirmed by the user in the UI, not silent). Templates are stored on `field_asset_types.default_checklist` for v1; a dedicated template table is deferred.

**field_asset_compliance** (things that expire): `field_asset_id`, `kind` (registration | insurance | dot_inspection | state_inspection | crane_cert | load_test | swing_stage_cert | calibration | warranty | other), `reference_number`, `issued_on`, `expires_on`, `issuer`, `cost`, `notes`. Drives the expiring-soon alerts. Registration, insurance, and cert expirations are usually the most business-critical part of fleet tracking.

**field_asset_documents** (document storage): `field_asset_id`, `field_asset_work_order_id` nullable, `field_asset_compliance_id` nullable, `inspection_id` nullable, `doc_type` (title | registration | insurance | manual | warranty | invoice | receipt | inspection_report | certification | photo | other), `label`, `original_name`, `file_path`, `file_size`, `file_mime`, `expires_on` nullable, `uploaded_by`, timestamps, soft deletes. A document can hang off the asset alone or off a specific work order / compliance record / inspection, so an invoice appears both on the work order and in the asset's file cabinet.

## Document storage

Copy the `JobDocumentController` pattern: private `local` disk (not `public`, since titles and insurance are sensitive), path `field_assets/{asset_id}/{uuid}.{ext}`, authenticated download route, original filename kept in the DB. Allowed types: pdf, images (jpg, png, webp, heic), doc/docx, xls/xlsx, csv, txt; 25 MB max. Support multi-file upload and phone camera capture (`accept="image/*" capture`), since techs will photograph invoices and damage in the field. Deleting a document soft-deletes the row and keeps the file until a purge job runs; removing a whole asset never hard-deletes files. Add `field_assets/` to the backup path check if backups are path-scoped (verify against `RecordBackupRun` / the backup script).

## Permissions

Added in a migration following `2026_09_10_000002_add_job_documents_permissions.php` (grant admin explicitly, since the frontend checks the literal list):

- `fleet.view`: see assets, history, documents (admin, manager, office_staff)
- `fleet.manage`: create/edit assets, plans, work orders, inspections, compliance (admin, manager)
- `fleet.documents.manage`: upload/delete documents (admin, manager, office_staff)
- `fleet.delete`: delete/retire assets (admin)
- `fleet.costs.view`: see purchase and maintenance cost figures (admin, manager), analogous to `pricing.view`
- Field crews do NOT get ForgeDesk accounts. They use a separate public-facing field app (not yet built) with its own limited logins; see "Field app sync boundary". So there is no `fleet.inspect` permission in ForgeDesk. Inspections arriving from the field app are attributed to a field-app user via `field_asset_inspections.submitted_by_external`, and office staff review them with `fleet.manage`.

Nav item uses `data-nav-permission="nav.fleet"` (check how existing `nav.*` permissions are seeded and mirror it). Every API route is protected with `permission:` middleware server-side.

## API (routes/api.php, inside the `v1` + `auth:sanctum` group)

Declare specific routes before parameterized ones, per repo convention. Controllers in `app/Http/Controllers/Api/FieldAsset*Controller.php`.

- `GET /fleet/dashboard`: counts by status, overdue/due-soon maintenance, expiring compliance (30/60/90 days), open work orders, and 12-month cost (cost figures gated by `fleet.costs.view`)
- `GET /fleet/upcoming`: unified due list (plans by date and meter, compliance, doc expirations)
- `apiResource /field-assets` plus `GET /field-assets/filter-options`, `POST /field-assets/{id}/meter`, `POST /field-assets/{id}/status`, `GET /field-assets/{id}/timeline` (merged history: work orders, inspections, compliance, meter, status changes)
- `apiResource /field-asset-types`
- `/field-assets/{id}/plans` plus `POST /field-asset-plans/apply-template` (apply a type template to an asset)
- `apiResource /field-asset-work-orders` plus `POST .../{id}/complete`
- `/field-assets/{id}/inspections`, `POST /field-asset-inspections/{id}/create-work-order`
- `/field-assets/{id}/compliance`
- `/field-assets/{id}/documents` (index, store, download, destroy) and the same nested under work orders
- `GET /fleet/reports/service-history/pdf` (dompdf, same approach as `serviceHistoryPdf`) and `GET /fleet/reports/costs` (per asset / per type / per period, Excel export via PhpSpreadsheet)

## UI

Blade shell views extending `layouts/app.blade.php` with page-local vanilla JS and Tabler components, same as the rest of the app. Do not copy the 1,132-line `maintenance.blade.php`; keep each view small, and split scripts per tab if they grow.

- `/fleet`: dashboard (status tiles, due soon/overdue list, expiring compliance, open work orders)
- `/fleet/assets`: filterable list (type, status, assigned user, job, overdue only), with search by number/VIN/plate/serial
- `/fleet/assets/{id}`: detail with tabs: Overview, Maintenance (plans + work orders), Inspections, Compliance, Documents, Timeline. Photo header, current meter with a quick "update meter" action.
- `/fleet/work-orders`: cross-asset work order board
- `/fleet/settings`: types, templates/checklists (admin)
- ForgeDesk's UI is office-oriented (desktop first, still usable on a tablet). Phone workflows (inspections, meter updates, defect photos) live in the field app, not here.
- QR code per asset (printable label PDF) encodes the `asset_number`. The field app resolves it; ForgeDesk's own page is for office use. Reserve `asset_number` as the QR payload now.
- Add an "Inbox" tab on `/fleet` listing field submissions that need review (rejected, conflicting, or flagged defects).

## Field app sync boundary

Constraint: ForgeDesk stays local-network only; the field app is the only public-facing piece and syncs with ForgeDesk through a database. Design principle: **the internet-facing side never holds a path into ForgeDesk's database, and ForgeDesk always initiates the connection (pull/push outbound).** The field app is untrusted input.

**Recommended topology.** The field app has its own database (its own Postgres, or a separate schema in a DMZ host). ForgeDesk talks to it over a dedicated connection, defined as a second Laravel DB connection (as was done for `cutflow`). Nothing public ever connects to the ForgeDesk `forgedesk` database. Do not point the field app at ForgeDesk's database, even with a restricted role, as the first compromise of the public app would land on ERP data (users, pricing, suppliers, everything).

Two flows, both run by scheduled ForgeDesk commands (`fleet:sync-out`, `fleet:sync-in`, every 1-5 min, `withoutOverlapping`):

1. **Out (ForgeDesk to field app), reference data, ForgeDesk is the source of truth.** Publishes a minimal projection into the field app's DB: active assets (`asset_number`, name, type, status, current meter, photo thumbnail, current job label), inspection checklists per type, and the field users list if ForgeDesk administers them. Exclude costs, purchase info, VINs/plates if not needed, documents, and anything else not required to do an inspection. Upsert by `asset_number` with a `synced_at` / `version` column; mark removed assets as inactive rather than deleting.
2. **In (field app to ForgeDesk), submissions, field app is the source of truth until ingested.** The field app only inserts into an append-only inbox: `inspection_submissions`, `meter_submissions`, `defect_reports`, `photo_uploads` (file blobs or object-store keys). Each row carries a client-generated UUID (`submission_uuid`) for idempotency, `asset_number`, `submitted_by` (field user id/name), `submitted_at` (device time) and the payload JSON. ForgeDesk pulls rows where `ingested_at IS NULL`, validates, writes to its own tables, then marks them ingested (the one column ForgeDesk updates, via its credentials).

**ForgeDesk-side tables**
- `field_sync_log`: `submission_uuid` (unique), `kind`, `status` (ingested | rejected | needs_review), `error`, `ingested_at`, `target_type`/`target_id`. Unique key makes ingestion idempotent if a run is retried or interrupted.
- `field_asset_inspections` gains `source` (office | field_app), `submission_uuid`, `submitted_by_external`, `submitted_at_device`.
- Meter readings from the field get `source = field_app` and are subject to the monotonic rule below.

**Ingestion rules (never trust the field app)**
- Validate everything as if it came from the internet: asset must exist and not be retired, enums in range, string lengths capped, numeric bounds, file type/size/magic-byte checks on photos, image re-encoding to strip scripts and EXIF GPS if not wanted.
- Meter reading lower than current: do not overwrite. Store as `needs_review` in the Inbox tab.
- A failed inspection item creates the `inspection_defect` work order automatically (status open, flagged "from field"), notifies `fleet.manage` users via `SystemNotification`, and proposes (does not silently apply) out-of-service. For designated safety-critical types, a fail-severity item may set `out_of_service` automatically: decide per type via a `field_asset_types.auto_out_of_service_on_fail` flag, default off.
- Late/offline submissions: accept by `submitted_at_device` but order and de-duplicate by `submission_uuid`. Cap how far in the past/future device time may be.
- Photos: pulled over the same connection (or an object store ForgeDesk reads from), written to the private `local` disk under `field_assets/{asset_id}/` and recorded in `field_asset_documents` (`doc_type = photo`, linked to the inspection/defect). After successful ingest, optionally purge the blob from the public side to limit exposure.
- Rejected rows are kept with a reason and shown in the Inbox, never silently dropped.

**Failure behaviour.** If the field DB is unreachable, ForgeDesk keeps working and the commands log and retry. The field app keeps working offline from its last published snapshot (PWA offline queue is the field app's concern; its `submission_uuid` makes replay safe). Surface "last successful sync" on the fleet dashboard with a warning if stale (>15 min).

**Contract.** Write the inbox/outbox schema and payload shapes as a versioned spec in `docs/` (JSON payload with `schema_version`), since two separate codebases depend on it. Include it in phase 1 even though the field app is built later.

**Alternative considered, rejected for now:** a public HTTPS API on ForgeDesk (Sanctum token for the field app). It is simpler to build but exposes ForgeDesk to the internet or needs a reverse-proxy carve-out, contrary to the local-only goal. A pull-based DB sync keeps the inbound attack surface at zero.

## Alerts and scheduling

- New artisan command `fleet:check-due` scheduled daily in `routes/console.php`. It finds plans within `warn_days`/`warn_meter` or overdue, and compliance/document expirations at 90/60/30/7 days and expired, then writes `SystemNotification` rows (de-duplicated per item and threshold). Optional email digest to users with `fleet.manage`, reusing the `app/Mail` pattern. Email is phase 2.
- Dashboard widgets via the existing customizable-dashboard registry (see `docs/plans/customizable-dashboard.md`; that work is paused on `feature/dashboard_ui`, so register widgets only once it resumes).

## Phasing

1. **Foundation**: types, assets, permissions, list/detail UI, document storage (upload/download/delete), status changes, nav entry. Delivers asset register plus file cabinet.
2. **Maintenance**: meter readings, plans (date + meter), work orders with cost lines, completion advancing plans, due calculations, `fleet:check-due`, dashboard.
3. **Compliance and inspections (office side)**: expiring items, inspection checklists and entry in ForgeDesk, defect to work order, out-of-service prompt. Also write the field sync contract spec here so the field app can be built against it.
4. **Field sync**: second DB connection, `fleet:sync-out` / `fleet:sync-in`, `field_sync_log`, ingestion validation, photo handling, Inbox tab, stale-sync warning. Testable against a local fake field DB before the real app exists.
5. **Reporting and polish**: service-history PDF, cost report/Excel, QR labels, parent/child asset tree, email digests, dashboard widgets.

## Testing

Per memory notes: never run bare `php artisan test` against the live DB; the host `composer test` is broken (PHP 8.3 vs 8.4), so run via `docker compose exec app php artisan test` after `config:clear`.

- Feature tests: permission gating per route, document upload/download authorization (private disk, no public URL), meter monotonic rule, plan advancement on work order completion (date-only, meter-only, and whichever-first), due/overdue boundaries, compliance expiry thresholds, de-duplication in `fleet:check-due`.
- Tests use SQLite in-memory, so avoid Postgres-only migration syntax.
- Sync tests use a second in-memory SQLite connection standing in for the field DB: idempotent re-ingest of the same `submission_uuid`, malformed/hostile payloads rejected, lower meter goes to review, photo type spoofing rejected, unreachable field DB leaves ForgeDesk unaffected, outbound projection contains no cost/purchase fields.

## Open questions

1. RESOLVED: field crews perform inspections through a separate public-facing field app with limited logins, syncing via DB (see "Field app sync boundary"). Still open: where does the field app's DB live (separate host/VPS vs DMZ schema), who administers field users (ForgeDesk-published list vs managed in the field app), and how are inspection checklists authored (ForgeDesk only, per this plan)?
2. RESOLVED (no): work order parts/costs are recorded as line items only; nothing deducts from ForgeDesk inventory. Drop the optional `product_id` link on `field_asset_wo_items`.
3. RESOLVED (no): no per-job billing or cost allocation. `field_asset_assignments` is dropped from the plan; `field_assets` keeps only current job/location/custodian fields.
4. Should compliance alerts be email as well as in-app, and to whom?
5. Is there existing fleet data (spreadsheet) to import at launch? If so, add a CSV import command in phase 1.
6. Should type-level maintenance templates (e.g. "Truck: oil change every 5,000 mi / 6 mo") be seeded with defaults, or left empty for the admin to define?
