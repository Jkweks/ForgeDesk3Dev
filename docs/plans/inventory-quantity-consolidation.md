# Inventory quantity consolidation (review + plan)

Written 2026-10-26 after a review of the inventory setup. Findings are from reading the code and querying the dev DB
(dev was reseeded from a prod backup, so re-run the checks against real prod before relying on the numbers).

## Goal

Bins (`inventory_locations.quantity`) are the only source of truth for stock. `products.quantity_on_hand` must never be
something the app can change independently of the bins.

## What we found

### 1. Several things still write `quantity_on_hand` without touching the bins
| Where | What happens |
|---|---|
| `Product::adjustQuantity()` (deprecated) | Called by `ProductController::adjustInventory`, `MachineToolingController` (x2). Order shipping was moved off it (354b8b8). |
| `ProductController::store` | Validates `quantity_on_hand` as required and writes it on create. No bin is created, so a new product with stock is "drifted" from birth. |
| `ImportExportController` (import) | Writes `Quantity On Hand` straight to the product on create *and* update. Same drift. |
| `InventoryLocationController::updateProductTotals` | A private copy of `Product::recalculateQuantitiesFromLocations()`. |
| `InventoryTransactionController::applyInventoryDelta(int ...)` | Edits bins correctly, but casts to `int` although quantities are decimal now (length-based parts), and deleting/editing a ledger row rewrites bins. |

### 2. Two competing definitions of `quantity_committed`
- `recalculateQuantitiesFromLocations()` sets `products.quantity_committed` = SUM of `inventory_locations.quantity_committed`.
- `recalculateCommittedQuantity()` sets it = bin-aware reservations + sales orders.
Whichever ran last wins. `inventory_locations.quantity_committed` is effectively dead (1 bin in dev has a non-zero value;
the reservation code only ever writes 0). Dev: 142 products have live reservations; the stored `quantity_committed` is wrong
for 46 of them (e.g. `P1180-0R` stored 0, live 30). The app mostly gets away with it because `quantity_available` and the
status use the live reservation figure, but anything reading the column (reports, `BusinessJobController`, exports) is stale.

### 3. The ledger cannot rebuild bins
`inventory_transactions` has `quantity_before/after` per *product* and no `storage_location_id`, so history cannot say which
bin a movement hit. This is why there are already two "reconcile ... from transaction history" migrations and the
`inventory:audit-transaction-mismatch` command. Rows can also be edited/deleted by an admin, which rewrites bins.

### 4. Smaller things
- `status` is stored but depends on reservations/PO state, so it goes stale (dev: 4 products had a stale status).
- `recalculate*` methods `save()` then `updateStatus()` saves again (double write on every movement).
- `quantity_available` runs a per-product reservation query on every serialization (N+1) unless the caller pre-assigns it.
- `on_order_qty` has both a running +/- tally in `PurchaseOrderController` and a recompute method; the comment admits the tally drifts.
- Transaction `type` is a DB enum that has been altered by 4+ migrations.
- Validation says `integer` for quantities that are decimal in the DB (migration 2026_09_24).
- Dev currently shows **0** products where `quantity_on_hand` != SUM(bins); prod may differ.

## Design options for the long-term fix

**A. Keep the column, make the database own it (recommended).** A trigger on `inventory_locations`
(insert/update/delete) recomputes `products.quantity_on_hand`. Any code path, import or tinker session stays consistent.
The app treats the column as read-only (mutator ignores/throws; remove every write). No read-site or SQL changes: sorting,
low-stock filters, reports keep working. Needs a SQLite twin for the test suite.

**B. Drop the column and compute it.** A global scope `withSum('inventoryLocations as quantity_on_hand', 'quantity')` keeps
`$product->quantity_on_hand` working, but the ~5 raw SQL uses (`DashboardController` sort, `SupplierController` value sum,
`InventoryReportsController` whereRaw, widget orderBy) and any report joins must become subqueries, `Product::create/update`
with the field needs a setter that writes a bin, and every product query pays a subselect. Cleaner end state, more risk.

Recommendation: A first. Revisit B only if we want the column gone entirely; A makes B a mechanical follow-up.

## Staged plan

1. **Writers** (no schema change): route every stock change through one service (`InventoryDeductor` plus a matching
   `receive/adjust` method). Remaining callers: tool installs, product adjust endpoint, product create, import.
   Delete `adjustQuantity()` and the duplicate `updateProductTotals`.
2. **Committed**: pick one definition (reservations + sales orders). Stop `recalculateQuantitiesFromLocations()` writing
   `quantity_committed`. Retire `inventory_locations.quantity_committed` (migrate away the one non-zero row first).
3. **Ledger**: add nullable `storage_location_id` to `inventory_transactions` and write it everywhere; stop editing/deleting
   ledger rows (reverse with a new row instead).
4. **Database ownership**: trigger from option A; make `quantity_on_hand` read-only in the model; nightly audit job.
5. **Cleanup**: decimal-aware validation, enum -> string + constants, `on_order_qty` single recompute, cache or eager-load
   `quantity_available`, tests for every writer.

## Before shipping any of it
Run the reconciliation check against **prod** (products where `quantity_on_hand` != SUM(bins), and stored vs live committed)
and agree on how to correct what it finds; fixing the writers changes visible numbers.
