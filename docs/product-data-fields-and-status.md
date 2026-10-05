# Product Data Fields, Pack Quantities, and Status

Reference for the quantity/pricing fields on `Product` (`app/Models/Product.php`), how they interact
with `pack_size`, and exactly how the computed `status` value is determined. All line numbers refer
to `laravel/app/Models/Product.php` unless noted otherwise.

## 1. Source of truth: `inventory_locations`

`Product.quantity_on_hand` and `Product.quantity_committed` are **denormalized totals**. The real
data lives in `inventory_locations` (one row per bin location per product), each with its own
`quantity` and `quantity_committed`.

`Product::recalculateQuantitiesFromLocations()` (`Product.php:208-225`) is the canonical way to
resync the product-level totals:

```php
$totals = $this->inventoryLocations()
    ->selectRaw('COALESCE(SUM(quantity),0) as total_quantity,
                 COALESCE(SUM(quantity_committed),0) as total_committed')
    ->first();

$this->quantity_on_hand   = $totals->total_quantity;
$this->quantity_committed = $totals->total_committed;
$this->save();
$this->updateStatus();
```

**Never write to `quantity_on_hand`/`quantity_committed` directly.** `Product::adjustQuantity()`
(`Product.php:379-404`) still does this in a couple of legacy call sites (`OrderController`,
`MachineToolingController`) — it's deprecated because it edits `quantity_on_hand` without touching
`inventory_locations`, so the two drift apart. New code should go through
`InventoryLocationController::adjust()`, which updates the location row first and then calls
`recalculateQuantitiesFromLocations()`.

`InventoryLocation` (`app/Models/InventoryLocation.php`) also has its own appended
`quantity_available = quantity - quantity_committed` — this is a per-bin figure, distinct from the
product-level `quantity_available` described below.

## 2. Raw quantity/pricing columns

| Column | Meaning |
|---|---|
| `quantity_on_hand` | Rolled-up total across all locations (in **eaches**, not packs) |
| `quantity_committed` | Rolled-up committed total — see §4, this is *not* the same number used by `quantity_available` |
| `minimum_quantity` / `maximum_quantity` | Stocking floor/ceiling used as the replenishment target |
| `reorder_point` | Threshold that drives most of `status` and `suggested_order_qty` |
| `safety_stock` | Secondary, lower threshold used to distinguish `low` from `very_low` |
| `average_daily_use` | Used for `days_until_stockout` and `calculateReorderPoint()` |
| `on_order_qty` | Outstanding quantity on open POs; recalculated by `recalculateOnOrderFromPurchaseOrders()` (`Product.php:238-253`) from PO lines with status in `draft, submitted, approved, partially_received` |
| `lead_time_days` | Used by `calculateReorderPoint()`: `reorder_point = (average_daily_use × lead_time_days) + safety_stock` (`Product.php:483-496`), falling back to `safety_stock` or 0 |
| `unit_cost` / `net_cost` | Cost fields. For pack-tracked products, `unit_cost` is the **cost of a whole pack**, not one each (see `pack_cost` below) |
| `pack_size`, `purchase_uom`, `stock_uom`, `min_order_qty`, `order_multiple` | Pack/UOM conversion inputs, see §3 |
| `is_active` | Lifecycle flag — whether the product is current at all. Independent of `status`. |
| `is_discontinued` | Manual lifecycle flag, independent of `status` — not queried anywhere outside `Product.php` currently, it's informational |
| `is_special_order` | Drives the dedicated "Special Order" dashboard tab regardless of stock status |

`is_active` / `is_discontinued` / `is_special_order` are **lifecycle** booleans. They are entirely
separate from the computed `status` state machine in §5 — a discontinued product can still carry a
stock `status` of `in_stock`, `low`, etc.

## 3. Pack quantities

Everything in the DB (`quantity_on_hand`, `quantity_committed`, reservation `committed_qty`, etc.) is
stored in **eaches**. `pack_size` (only meaningful when `> 1`, checked via `hasPackSize()`,
`Product.php:578-581`) converts eaches to/from whole packs for display and ordering math.

The conversion helpers (`Product.php:552-627`) and the rule used throughout the codebase:

- **On-hand / available in packs → floor.** You can't display or claim a partial pack as available.
  `eachesToFullPacks()` = `floor(eaches / pack_size)`.
- **Committed / needed in packs → ceil.** A partial-pack commitment still ties up a whole pack.
  `eachesToPacksNeeded()` = `ceil(eaches / pack_size)`.

This floor/ceil split is applied consistently:

| Appended field | Formula |
|---|---|
| `quantity_on_hand_packs` | `floor(quantity_on_hand / pack_size)` |
| `quantity_available_packs` | `floor(quantity_on_hand_packs − committed_packs_from_reservations)`, where `committed_packs_from_reservations` = `ceil(committed_from_reservations_in_eaches / pack_size)` |
| `counting_unit` | `purchase_uom` (or `'packs'` fallback) when `hasPackSize()`, else `stock_uom` (or `'EA'`) |
| `pack_cost` | Just `unit_cost` — since for pack products `unit_cost` already represents the pack's cost |

Reports/exports (`ImportExportController`, `ReportsController`, `StorageLocationController`,
`MaterialCheckController`, `CycleCountController`) all replicate this same floor-on-hand /
ceil-committed convention when converting eaches ↔ packs for display, rather than calling back into
the Product accessors.

`suggested_order_qty` (§4) additionally rounds the *suggested order amount itself* up to a whole
number of packs when `pack_size > 1`, on top of rounding to `order_multiple` and enforcing
`min_order_qty`.

## 4. `quantity_available` and reservation commitments

This is the field almost everything else (`status`, `suggested_order_qty`, `days_until_stockout`)
is built on:

```php
// Product.php:339-346
quantity_available = floor(quantity_on_hand − committed_from_reservations)
```

`committed_from_reservations` (`Product.php:294-297`) is **not** the stored `quantity_committed`
column — it's a live call to `JobReservationItem::binAwareCommitted($productId)`
(`app/Models/JobReservationItem.php:107-153`), summed only over reservations with status
`active`, `in_progress`, or `on_hold` (not soft-deleted).

That method does **bin-packing**, not a simple sum, because reservation `committed_qty` values are
fractional lengths (in tenths) cut from stick/length-based stock: each unit of stock is a "stick" of
length 1.0, and reservation items are greedily packed onto sticks. If a stick's leftover remainder is
smaller than the smallest pending reservation item, that whole stick counts as consumed (nothing else
can be cut from it). The result is the number of whole/partial sticks effectively tied up — which can
be *less* than the naive sum of `committed_qty` values, because multiple small reservations can share
one stick.

**Why this matters:** the stored `quantity_committed` column is a different, broader number —
`recalculateCommittedQuantity()` (`Product.php:262-270`) sets it to
`binAwareCommitted() + CommittedInventory::sum('quantity_committed')` (job reservations *and*
sales-order commitments combined), while `recalculateQuantitiesFromLocations()` instead rolls it up
from `inventory_locations.quantity_committed`. These two paths for populating `quantity_committed`
can diverge from each other and from `committed_from_reservations`. `quantity_available` deliberately
ignores the stored column and always recomputes from live reservations, because that's the number
that determines whether new reservations can be made.

Because `binAwareCommitted()` is expensive (it queries and packs every active reservation item),
bulk list endpoints (e.g. `DashboardController`) skip the accessor by writing
`quantity_available` directly into `$product->attributes` from a cheaper aggregate query — the
accessor checks `array_key_exists('quantity_available', $this->attributes)` first and returns that
value as-is if present, rather than recomputing.

## 5. How `status` is determined

`status` is a plain DB column, but it is **only ever set by `Product::updateStatus()`**
(`Product.php:348-377`) — nothing else writes to it directly. It's called at the end of
`recalculateQuantitiesFromLocations()`, `recalculateOnOrderFromPurchaseOrders()`,
`recalculateCommittedQuantity()`, and `adjustQuantity()`, so it should always reflect the latest
quantities.

Logic, evaluated in this order:

```php
$available    = quantity_available;
$reorderPoint = reorder_point ?? 0;
$safetyStock  = safety_stock ?? 0;
$onOrder      = on_order_qty ?? 0;

if ($available <= 0) {
    $status = $reorderPoint > 0 ? 'critical' : 'out_of_stock';
} elseif ($available > $reorderPoint) {
    $status = 'in_stock';
} elseif ($available > $safetyStock) {
    $status = 'low';
} else {
    $status = 'very_low';
}

// Override: an already-ordered shortage reads as "on_order", not a stock alarm
if ($status !== 'in_stock' && $onOrder > 0 && ($available + $onOrder) > $reorderPoint) {
    $status = 'on_order';
}
```

| Status | Condition |
|---|---|
| `in_stock` | `quantity_available > reorder_point` |
| `low` | `safety_stock < quantity_available ≤ reorder_point` |
| `very_low` | `quantity_available ≤ safety_stock` (and `> 0`) |
| `critical` | `quantity_available ≤ 0` and `reorder_point > 0` is set |
| `out_of_stock` | `quantity_available ≤ 0` and no `reorder_point` is set |
| `on_order` | Overrides any of the above (except `in_stock`) when `on_order_qty > 0` and `quantity_available + on_order_qty > reorder_point` — i.e. a PO is already in flight and will clear the shortfall |

This is the same threshold as `needsReorder()` (`Product.php:709-718`):
`(quantity_available + on_order_qty) <= reorder_point`.

The full enumerated set of stock statuses used across the app (dashboard counts, reports, supplier
reorder views) is exactly these six: `in_stock`, `low`, `very_low`, `critical`, `out_of_stock`,
`on_order`. The dashboard's "Low Stock" tab groups `low` + `very_low` together; there is no separate
`'active'`/`'discontinued'` status string — those live only on the independent `is_active` /
`is_discontinued` booleans (§2).

## 6. `suggested_order_qty` and `days_until_stockout`

`suggested_order_qty` (`Product.php:431-465`) only produces a nonzero value when a reorder is
actually needed (same trigger as `needsReorder()`):

```php
if (!reorder_point) return 0;
if ((quantity_available + on_order_qty) <= reorder_point) {
    $target = maximum_quantity ?: (reorder_point * 2);
    $qty = $target - quantity_available - on_order_qty;
    if (order_multiple > 1) round $qty up to a multiple of order_multiple;
    if ($qty < min_order_qty) $qty = min_order_qty;
    if (pack_size > 1) round $qty up to a whole number of packs;
    return max(0, $qty);
}
return 0;
```

`days_until_stockout` (`Product.php:470-477`) is simply
`round(quantity_available / average_daily_use, 1)`, or `null` if `average_daily_use` isn't set
or is zero.

## Summary

- `inventory_locations` is the source of truth; `Product.quantity_on_hand`/`quantity_committed` are
  rolled-up copies kept in sync by `recalculateQuantitiesFromLocations()`.
- `quantity_available` is a **live** number (`quantity_on_hand` minus a bin-packed reservation
  calculation), not derived from the stored `quantity_committed` column.
- Pack conversions always floor "on hand"/"available" and ceil "committed"/"needed"; `unit_cost` is
  already a per-pack cost for pack-tracked products.
- `status` is a derived field with exactly six possible values, computed solely by
  `updateStatus()` from `quantity_available`, `reorder_point`, `safety_stock`, and `on_order_qty` —
  it is unrelated to the `is_active`/`is_discontinued`/`is_special_order` lifecycle flags.
