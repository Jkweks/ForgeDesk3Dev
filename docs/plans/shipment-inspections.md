# Shipment pre/post-delivery inspections

## Context
ForgeDesk has no shipment record. Crews need a checklist before a delivery leaves the shop (pre, with
photos from a phone) and one on arrival (post, in the separate **ForgeDesk Field** PWA). Checklists differ by
what is shipped (metal, glass, doors, ...) and an admin must be able to add new types and their data points.
Decisions (confirmed with user): new Shipment entity tied to a BusinessJob; admin-managed types each with
their own pre and post items; post-check finds shipments via a published list through the existing field sync.

## ForgeDesk (laravel/)

**Migrations** (`2026_10_30_00000x_*`, same style as the fleet ones):
- `shipment_types`: name, slug, is_active, sort_order.
- `shipment_check_items`: shipment_type_id, `phase` (pre|post), label, `input_type` (pass_fail | number | text | choice), unit, options JSON, required, photo_required, critical, sort_order.
- `shipments`: shipment_number (SHP-00001, like `FieldAssetWorkOrder::nextNumber()`), business_job_id, status (`draft`, `pre_check_done`, `shipped`, `delivered`, `delivered_issues`), ship_date, destination, carrier/driver, notes, created_by.
- `shipment_loads`: shipment_id, shipment_type_id, description/piece count (a shipment can carry several types).
- `shipment_inspections`: shipment_id, phase, result (pass|fail|needs_attention), performed_by (user id or external name), performed_at, notes, source (web|field_app), submission_uuid unique.
- `shipment_inspection_items`: inspection_id, shipment_type_id, **snapshot** of label/input_type/critical, value, result, comment.
- `shipment_inspection_photos`: inspection_id, item_id nullable, file_path (private `local` disk), mime, size.
- Permissions migration modelled on `2026_10_25_000004_add_fleet_permissions.php`: `shipments.view`, `shipments.manage`, `shipments.inspect`, `shipments.types.manage`, `nav.shipments`.
- Seed starter types (Metal, Glass, Doors, Hardware) with sensible pre/post items (count matches packing list, damage/scratches, protective film, crating/securement, glass edge chips, door size/swing/handing, etc.) so it is usable day one; admins edit from there.

**Models/services**: `Shipment`, `ShipmentType`, `ShipmentCheckItem`, `ShipmentInspection(+Item,+Photo)`; `app/Services/Shipping/ShipmentInspectionService` (validate answers against the *ForgeDesk-held* item definitions, compute result: any failed/`critical` item => fail, set shipment status, raise alert). Extract `reencode()`/image-sniffing from `FieldSync` (app/Services/Fleet/FieldSync.php) into a shared `ImageSanitizer` and use it from both (strips EXIF/GPS).

**API** (`routes/api.php`, specific routes before `{id}`; server-side `permission:` middleware): `ShipmentController` (CRUD, list per job, mark shipped), `ShipmentTypeController` (types + items CRUD, reorder), `ShipmentInspectionController` (list, create pre-check via multipart with photos, photo download).

**UI** (Blade + vanilla JS + Tabler like `resources/views/fleet/`): `shipments/index.blade.php` (list, create from a job, filters), shipment detail with load types, pre-check form (generated from the type items, big touch targets, `<input type=file accept="image/*" capture="environment" multiple>` for phone camera, client-side downscale like field-app `shrinkImage`), post-check results viewer with photos, and a "Types & checklists" admin tab (add type, add/reorder items, set input type / photo-required / critical). Nav entry under Jobs in `config/navigation.php` and `navigation_classic.php`; a Shipments tab on the job page.

**Field sync (contract v3, additive)** in `FieldSync` + `docs/fleet-field-sync-contract.md`:
- Out: publish `fa_shipments` = open shipments (status `shipped`, not yet post-checked): shipment_number, job label, destination, ship_date, `checklist` JSON (per load type: its **post** items with input_type/options/required/photo_required; no `critical` flag, as with fleet). No costs or internal notes.
- In: new submission kind `shipment_inspection` (shipment number carried in `asset_number` column, so `fa_submissions` is unchanged) with payload `{notes, items:[{type, label, value, result, comment, photo_index?}]}`; `process()` branches on kind before the asset lookup. Reject unknown shipment/not shipped-yet => `needs_review`; validate; photos pulled via the existing `attachPhotos` path (rewritten to take a link target); idempotent via `submission_uuid`.

## Field app (field-app/)
- `db/init/01-schema.sql` + `db/upgrades/003-shipments.sql`: `fa_shipments` table; grants in `02-roles.sh`.
- `server/validate.js`: allow kind `shipment_inspection` (shipment must exist in `fa_shipments`), validate items/values/photos (reuse photo sniffing); `routes.js` `/bootstrap` returns shipments; submission insert unchanged.
- `public/app.js`: "Deliveries" section on home, shipment list (from cached bootstrap, works offline), post-inspection form per load type rendering the dynamic input types, per-item and general photos via the existing `photoPicker` (camera capture), queue through existing IndexedDB outbox/`flush()`, KIND label in queue view; bump `sw.js` cache version.

## Tests
- ForgeDesk (`composer test` is broken on host; run `docker compose exec app php artisan test` per memory, never against live DB): type/item CRUD + permissions, pre-check create w/ photos (non-image rejected), result/status logic, `FleetFieldSyncTest`-style ingest tests for `shipment_inspection` (idempotency, unknown shipment, not-shipped hold, spoofed critical flag ignored), publish contains only open shipments.
- Field app: extend `field-app/test` for validate.js and bootstrap.

## Verification
Create a type + items in the admin tab -> create shipment on a job with two load types -> pre-check from a phone (camera photos) -> mark shipped -> run `fleet:sync-out` -> shipment appears in field app -> do post-check offline, reconnect -> `fleet:sync-in` -> inspection + photos visible on shipment, fail item raises alert and sets `delivered_issues`.

## Rollout notes
Run field-app `003-shipments.sql` upgrade before ForgeDesk publishes (sync-out fails loudly otherwise, as with v2). Copy this plan to `docs/plans/shipment-inspections.md` when implementation starts.
