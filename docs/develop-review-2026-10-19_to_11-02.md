# Develop Branch Review — 19 Oct to 2 Nov 2026

**Scope:** ~45 non-merge commits on `develop`, 239 files changed (+23.8k / −6.3k lines), plus uncommitted work in the working tree.

## Summary

The two weeks fall into five themes:

1. **Shop floor scheduling:** the work queue became a real schedule, and a Programming Queue was added.
2. **Field / shipping:** a new offline mobile app, shipment inspections, and shipping reports.
3. **Inventory integrity:** stock quantities are now owned by the database.
4. **UI overhaul:** most of the major list pages were reworked.
5. **CutFlow and Configurator:** reporting, tiger-bridge packaging, and data-driven hardware formulas.

---

## 1. Fabrication: Work Queue, Schedule, Programming Queue

- **Work queue (23–25 Oct):**
  - The board can be grouped by job or by step.
  - A whole job, or chosen steps of a job, can be bulk-assigned in one action.
  - Cards are labelled "job name – release", with the job number as a trailing reference.
  - Filters, summaries and safer kiosk refresh were added for the shop floor displays.
  - A printable calendar was added.
- **Schedule (26 Oct):**
  - New `fd_schedule_entries` table.
  - Multi-day bars in quarter-day slots.
  - An unassigned panel, assists and notes, and scope/step assignment.
  - A matching print view, a dashboard widget (1-week), and inline work order assignees.
  - The schedule is now the default work queue view.
- **Programming Queue (30 Oct):**
  - New Fabrication > Programming Queue page.
  - It has a list view and a 2-week calendar. The calendar places programming the working day before each work order's planned fab start.
  - Programming steps default to the configured programmer (`config/fabrication.php`).
  - A migration reassigns programming steps that are still to do. Completed history is left alone.
- **Jobs page:** permanent status, status tabs with counts, overdue/due-soon stats, sortable headers, and URL state.
- **Fabrication documents:**
  - Preview pane, tag/type/hardware/manufacturer filters, and drag-and-drop upload.
  - Replacing a file keeps the old one as a revision, with restore and delete endpoints.
  - Fixed a selection-id mismatch in merged export.

## 2. ForgeDesk Field (mobile app) and Shipping

- **Field app (25 Oct, new `field-app/`):**
  - Self-contained, with its own docker-compose and Postgres, so it can be deployed on a separate server.
  - Offline-first PWA: QR scan, inspections from published checklists, meter readings, and defect reports with photos.
  - Outbox with idempotent submission UUIDs.
  - Security hardening: CSRF guard, CSP headers, image content-sniffing, DB-backed sessions, login rate limiting, TLS, and least-privilege Postgres roles.
- **Mobile logins** use ForgeDesk accounts via `users.mobile_app_access`.
  - It is set by an admin-only checkbox.
  - `fleet:sync-out` publishes opted-in users to `fa_users`.
  - A password hash is present only while the account can sign in.
- **Sync contract:** `docs/fleet-field-sync-contract.md` went from v1 to v4, with upgrade SQL scripts `003` and `004` for existing field databases.
- **Shipment inspections (2 Nov):**
  - Shipments, shipment types, check items and inspections.
  - `ShipmentInspectionService`.
  - A shipments web UI (index, queue, show).
- **Shipping portal and reports:**
  - A business_job↔shipment pivot.
  - Shipment PDF and shipping summary report.
  - Image sanitizer.
- **Field app v4:** Queue, Pre-check and Deliveries tabs; shipments can be created from the phone; per-user permissions; job and shipment types in the snapshot.

## 3. Inventory Integrity

- **Database-owned `quantity_on_hand` (26 Oct):**
  - A Postgres/SQLite trigger on `inventory_locations` keeps `products.quantity_on_hand` equal to the sum of the live bins.
  - Product create, import, adjust and tool installs all go through `InventoryDeductor`, which is now the single stock writer.
  - The deprecated `Product::adjustQuantity()` was removed.
  - The Product saving guard ignores direct writes that disagree with the bins.
  - New `inventory:check-totals` command (daily schedule, `--fix`).
  - Plan: `docs/plans/inventory-quantity-consolidation.md`.
- **Order shipping bug fixed:** shipping edited `quantity_on_hand` directly, so the shipped units came back on the next recalculation. It now goes through `InventoryDeductor`, which locks the bins it reads.
- **Replenishment:** committed parts with nothing on hand are now critical and suggested for ordering.
- **Storage locations:**
  - Master-detail UI.
  - Bulk child creation, in-screen transfers, and an include-children view.
  - Type-ahead location picker on the product modal.
  - The standalone Assign Locations page and the admin Location Assignment tab were removed.

## 4. UI Rework (23 Oct)

- **Page reworks:** Products (filter bar, URL-synced state, location and category columns), Purchase Orders (status tabs, slim stats), Replenishment (collapsible vendors, selection bar), and the product modal (sticky tabs, compact stats).
- **Admin page:** grouped sidebar navigation replaces the tab strip, and the page is split into per-section partials.
- **Dashboard:** new widgets in the widget registry.

## 5. CutFlow and Configurator

- **CutFlow:**
  - Cut-list lines are kept separate per elevation and opening position.
  - Part photo lookup by shape.
  - New cut-station reports: productivity, recuts, and waste (`CutStationStats`).
  - Cut-list ingest and job picker improvements.
  - Admin download of the tiger-bridge package as a zip (source only, token blanked).
- **Configurator (2 Nov):**
  - Hardware variable formulas are now data-driven (`formula_def` with signed terms and conditional branches), replacing per-code `selectBranch` logic in `HwlibResolver`.
  - A migration converts the 16 existing formulas.
  - Saving validates unknown codes and circular references.
  - Hardware items can have uploaded documents: templates, instructions, parts manuals and spec sheets.

## 6. Tests and Bug Fixes

- **New feature tests:** ScheduleEntry, InventoryEndpoints, InventoryAdminApi (locations, suppliers, cycle count lifecycle, roles), ApiSmoke, InventoryQuantityTrigger, OrderShipInventory, FabricationDocumentRevision, CutStationStats and CutStationJobPicker.
- **Bugs found by the new tests and fixed:**
  - Optional `notes` crashed bin adjust/transfer, manual transactions, transaction update and product issue.
  - The inventory report PDF always failed because the template read `nonsof`, which the PDF data omitted.

## 7. Uncommitted (working tree)

- **CutFlow printer on/off toggle:**
  - New migration `database/migrations/cutflow/2026_11_02_000001_add_printer_enabled_to_cutflow_settings.php`.
  - Changes to `CutFlowSetting`, `TigerBridgeClient`, the CutFlow Dashboard and Settings components and views.
  - New `CutFlowPrinterToggleTest`.
  - The printer defaults to on.

## Things to Watch

- The stock trigger is a behavioural change. Any remaining code that writes `quantity_on_hand` directly will be silently overridden. Run `inventory:check-totals` after deploy.
- New migrations need to run on deploy: the schedule tables, fabrication document revisions, programming reassignment, hwlib `formula_def` and item documents, the business_job↔shipment pivot, and the stock trigger.
- Existing field-app databases need the `003` and `004` upgrade SQL, and the field app must be redeployed to match contract v4.
- The CutFlow printer-toggle migration targets the separate `cutflow` DB connection.
- The Programming Queue hardcodes a default programmer in config. Confirm that is intended for other environments.
