# Shipment flow: criteria library, categories, auto-created shipments, elevations, splits

Builds on `shipment-inspections.md` (shipments, `shipment_types`, pre/post inspections, field-app sync).
Decisions confirmed with user (2026-11-10): partial delivery = **flag + note only**, and a partially delivered
elevation **stays selectable on future shipments**; auto-created shipment is a **draft holding every elevation**,
split by **moving** elevations to additional shipments.

## Vocabulary
- **Criterion** (new): a reusable check definition ("No scratches", "Piece count matches packing list").
- **Category** = the existing `shipment_types` (Metal, Glass, Doors...). Now a named set of criteria, not an owner of item rows.
- **Shipment** carries categories (existing `shipment_loads`) and, new, elevations.

## 1. Criteria library -> categories -> shipment

Today `shipment_check_items` rows belong to exactly one type, so the same check is retyped per type.

- New `shipment_criteria`: label, input_type, unit, options, default `required`, `photo_required`, `critical`, is_active, sort_order.
- New pivot `shipment_type_criterion`: shipment_type_id, criterion_id, `phase` (pre|post), sort_order, nullable overrides for required/photo_required/critical (null = use the criterion default). The same criterion can be on pre and post.
- **Migration**: turn each existing `shipment_check_items` row into a criterion (dedupe on label+input_type+unit+options) and a pivot row, preserving phase/order/flags as overrides when they differ. Keep `shipment_check_items` until verified, then drop in a later migration.
- `ShipmentType::itemsFor($phase)` keeps its signature but reads through the pivot and returns the same shape, so `ShipmentInspectionService`, the web pre-check form and the field-sync `checklist` publish need no contract change. Inspection items already snapshot label/type, so history is unaffected by criterion edits.
- Editing a criterion propagates to every category using it (that is the point of a standard); the admin UI shows "used in N categories" before saving.
- Admin UI (`shipments` types tab): two panes, **Criteria** (CRUD, search) and **Categories** (pick criteria per phase from the library, drag to order, per-category override toggles). Adding a category to a shipment is unchanged (loads).
- API: `/shipment-criteria` CRUD; `PUT /shipment-types/{id}/criteria` replaces the attached set. Permission `shipments.types.manage`.

## 2. Auto-created shipment on work order entry

- `FdWorkOrder::created` (model boot already hooks creation, so UI, EZ Estimate import and any other path are covered) creates a **draft** shipment: next `SHP-` number, linked to the WO's `business_job`, `work_order_id` set, destination from the job's site address, `ship_date` from the WO `due_date`.
- New `shipments.work_order_id` (nullable FK, nullOnDelete). The existing job link (`business_job_shipment`) is filled too so current lists/filters keep working.
- Elevations: `FdWoElevation::created` attaches the new elevation to the WO's earliest open draft shipment (WOs get elevations after the WO row exists). If no draft remains, the elevation simply shows as unscheduled.
- Default categories: optionally derive from elevation type (e.g. Door/Frame -> Doors, Storefront -> Metal + Glass) via a nullable `default_shipment_type_id` on `fd_elevation_types`. If unset, the shipment starts with no categories and the user adds them. (Open question below.)
- Complete/cancelled WO: cancel its still-draft shipments; do not touch shipped ones.
- No backfill for existing WOs by default; an artisan command `shipments:backfill-drafts` for open WOs if wanted.

## 3. Elevations on a shipment, splits, partial delivery

- New `shipment_elevations`: shipment_id, fd_wo_elevation_id, `partial` bool, `partial_note`, timestamps; unique (shipment_id, elevation_id). An elevation may appear on **several** shipments (that is what keeps a partial one selectable again).
- Shipment detail gets an **Elevations** card: elevations of the WO(s) on the shipment, each with tag, type, qty, and badges for status across all shipments (not scheduled / on SHP-xxxxx / delivered / partial). Checkbox add/remove while the shipment is draft or pre_check_done.
- **Split**: "Split shipment" button creates a sibling draft for the same WO (same job, destination, categories copied) and opens a move dialog: tick elevations to move over. Move = delete pivot row on source, add on target, in one transaction. Rules: cannot move off a shipped/delivered shipment; shipments of one WO are listed together ("Trip 1 of 3") on the show page and queue.
- **Unscheduled list** on the WO side and in the shipments index: elevations of open WOs that are on no open shipment, so nothing silently falls off a split.
- **Partial**: toggle "Partially delivered" + note per elevation on a shipment (editable until verified, and by the field-app post-check later if wanted). Delivering a shipment marks its non-partial elevations delivered; a partial one is **not** delivered, so it keeps showing as selectable. A WO is "fully delivered" only when every elevation has a non-partial delivered row on a delivered shipment.
- Elevation delivered/partial state is derived from the pivot + shipment status, not stored on the elevation, to avoid drift.
- Report PDF and queue/field-sync `fa_shipments` publish: add elevation tags (+ partial flag) to the job label/detail; additive to contract v3, field app can ignore it at first.

## Files touched (indicative)
- Migrations: criteria + pivot + data migration; `shipments.work_order_id`; `shipment_elevations`; optional `fd_elevation_types.default_shipment_type_id`.
- Models: `ShipmentCriterion` (new), `ShipmentType`, `Shipment` (+`workOrder`, `elevations`, siblings), `FdWorkOrder`/`FdWoElevation` (hooks, `shipments`).
- `ShipmentController` (store/update accept `elevation_ids`; new `split`, `elevations` endpoints; detail adds elevations), `ShipmentTypeController` (criteria endpoints), `ShipmentInspectionService` (read via pivot), `FieldSync` publish.
- Views: `shipments/show.blade.php` (elevations, split), `shipments/index`/`queue` (trip grouping, unscheduled), types admin tab.

## Tests
Criteria migration dedupe + overrides; `itemsFor` parity before/after; WO create -> draft with elevations; elevation added later joins draft; split/move rules and permissions; partial elevation remains selectable and WO not "fully delivered"; cancel WO cancels drafts only. Run via `docker compose exec app php artisan test` after `config:clear` (never against live DB).

## Build order
1. Criteria library + category linkage (self-contained, includes data migration).
2. `work_order_id` + elevations pivot + auto-draft hooks.
3. Elevations card, split/move, partial flag, unscheduled list.
4. Report/queue/field-sync additions.

## Open questions
- Default categories per elevation type (section 2): want this, or add categories by hand?
- Backfill drafts for currently open work orders?
- Should the quantity-short case ever matter later (flag+note is chosen for now; the pivot can gain `qty` without a redesign)?

## Implemented 2026-11-10 (deviations from the plan above)
- Criteria link via `shipment_check_items.criterion_id` (category rows stay materialized, flags are per-category overrides) instead of a new pivot; inspection service, tests and field-sync contract untouched. Label/type/unit/options edits on a criterion propagate to all linked rows.
- Open questions answered: default category per elevation type = yes (`fd_elevation_types.default_shipment_type_id`, set in Admin > Elevation types); backfill drafts = yes (see below); qty = later.
- Jobs whose number starts with `3` get no auto draft (`WorkOrderShipmentService::EXEMPT_JOB_PREFIXES`); shipments for them are created by hand and elevations picked on the shipment page.
- Completing a work order does NOT cancel drafts (fab-complete is not delivery).
- Picking an elevation that is on another not-yet-shipped shipment moves it; no separate move endpoint.
- Field-sync publish / field app NOT changed (elevations are office-side only for now); PDF report lists elevations.
