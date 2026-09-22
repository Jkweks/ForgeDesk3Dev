# Order Replenishment Workflow

## Overview

The replenishment workflow monitors inventory levels against configurable thresholds and provides a UI for creating purchase orders to restock low-inventory products. POs follow a structured approval lifecycle before goods are received back into inventory.

---

## 1. Inventory Status & Trigger Logic

Products are continuously evaluated against thresholds. Status is calculated by `Product::updateStatus()`:

| Status | Condition |
|---|---|
| `in_stock` | `quantity_available > reorder_point` |
| `low` | `quantity_available <= reorder_point AND > safety_stock` |
| `very_low` | `quantity_available <= safety_stock` |
| `out_of_stock` | `quantity_available == 0` |
| `critical` | `quantity_available < 0` |

**Key fields driving these calculations:**

| Field | Description |
|---|---|
| `quantity_available` | `quantity_on_hand - quantity_committed` (committed = reserved by job reservations) |
| `on_order_qty` | Quantity already on open/approved POs — excluded from available, but included in reorder check |
| `reorder_point` | Configurable per-product threshold; can be auto-calculated as `(average_daily_use × lead_time_days) + safety_stock` |
| `safety_stock` | Minimum buffer level below reorder point |

**Reorder trigger:** `Product::needsReorder()` returns true when `(quantity_available + on_order_qty) <= reorder_point`

---

## 2. Suggested Order Quantity Calculation

`Product::getSuggestedOrderQtyAttribute()` computes the recommended order quantity:

1. If `reorder_point` is not set → return 0 (no suggestion)
2. If `needsReorder()` is false → return 0
3. Otherwise:
   - **Target** = `maximum_quantity` OR `reorder_point * 2` (if no max is set)
   - **Raw qty** = `target - quantity_available - on_order_qty`
   - Apply `min_order_qty` floor (if set)
   - Round up to `order_multiple` (if set)
   - Round up to whole packs via `pack_size` (ceiling division)
   - Floor at 0

---

## 3. Replenishment UI (`/operations/replenishment`)

The replenishment page is the primary workflow surface for identifying what needs to be ordered.

**Data loading:**
- Fetches all products with status `critical`, `very_low`, `low`, or `out_of_stock`
- Excludes products with no `supplier_id`
- Excludes `out_of_stock` items unless `maximum_quantity > 0`

**Display:**
- Groups items by supplier
- Summary cards: Out of Stock, Critical, Very Low, Low, Estimated PO Total
- Per-item metrics: available qty, reorder point, on-order qty, days until stockout
- `suggested_order_qty` pre-filled and user-editable
- Sortable columns: SKU, description, available, reorder point, days left, unit cost, line total, status

**Auto-selection:**
- Items with `status != 'in_stock'` AND `suggested_order_qty > 0` are pre-checked
- In-stock items are never pre-selected

**Filtering:**
- By status, vendor, free-text search (SKU / description)

---

## 4. PO Creation Flow

From the replenishment UI, the user selects items per vendor and clicks **Create PO** (per vendor) or **Create All POs**:

1. A modal collects: PO Number (required, must be unique), Order Date, Expected Date (optional), Notes
2. `POST /api/v1/purchase-orders` is called with all selected line items
3. On creation each product's `on_order_qty += quantity_ordered` immediately
4. PO is created in `draft` status
5. The replenishment list reloads, and ordered items drop off if `on_order_qty` now satisfies `needsReorder()`

---

## 5. PO Status Lifecycle

```
draft → submitted → approved → [partially_received →] received
  ↓          ↓           ↓              ↓
cancel     cancel      cancel         cancel
```

Inventory impact at each transition:

| Transition | Actor | Inventory Impact |
|---|---|---|
| Create (draft) | Any user | `product.on_order_qty` increases |
| Submit | Any user | None |
| Approve | Any user | None |
| Receive items | Any user | `on_order_qty` decreases, `quantity_on_hand` increases, `InventoryTransaction` created |
| Cancel | Any user | `on_order_qty` released for all unreceived quantities |
| Delete (draft only) | Any user | `on_order_qty` released for all items |

> **Note:** There is currently no permission gate on approval — any authenticated user can approve a PO.

---

## 6. API Endpoints

All routes require `auth:sanctum` and are prefixed `/api/v1/`.

### CRUD

| Method | Route | Description |
|---|---|---|
| `GET` | `/purchase-orders` | List POs — filterable by `status`, `supplier_id`, `date_from`, `date_to`, `search` |
| `POST` | `/purchase-orders` | Create PO (auto-generates `po_number` if omitted) |
| `GET` | `/purchase-orders/{id}` | Get single PO with supplier, items, creator |
| `PUT` | `/purchase-orders/{id}` | Update PO header fields (draft only) |
| `DELETE` | `/purchase-orders/{id}` | Delete PO (draft only) |

### Workflow Actions

| Method | Route | Description |
|---|---|---|
| `POST` | `/purchase-orders/{id}/submit` | Draft → Submitted (requires ≥ 1 item) |
| `POST` | `/purchase-orders/{id}/approve` | Submitted → Approved |
| `POST` | `/purchase-orders/{id}/receive` | Receive items (see below) |
| `POST` | `/purchase-orders/{id}/cancel` | Cancel from any state except `received` |

### Line Item Management

| Method | Route | Description |
|---|---|---|
| `POST` | `/purchase-orders/{id}/items` | Add line item (draft only) |
| `DELETE` | `/purchase-orders/{id}/items/{itemId}` | Remove line item (draft only) |

### Query / Reporting

| Method | Route | Description |
|---|---|---|
| `GET` | `/purchase-orders-open` | All open POs (`submitted`, `approved`, `partially_received`) |
| `GET` | `/purchase-orders-statistics` | Counts and values by status |

---

## 7. Receiving Workflow

`POST /api/v1/purchase-orders/{id}/receive` accepts a batch of items. The PO must be in `approved` or `partially_received` status.

**Request body:**
```json
{
  "items": [
    {
      "item_id": 1,
      "quantity": 50,
      "storage_location_id": 3,
      "notes": ""
    }
  ]
}
```

**Per item:**
- `quantity_received` incremented on the `PurchaseOrderItem`
- `product.on_order_qty` decremented by the received quantity
- `product.quantity_on_hand` incremented by the received quantity
- `InventoryTransaction` created (`type = 'receipt'`, `reference_type = 'purchase_order'`)
- If `storage_location_id` is provided, the `InventoryLocation` record is updated or created

**PO status after receive:**
- All items fully received → `received`
- Some items still outstanding → `partially_received`

---

## 8. Reports & Priority Scoring

`GET /api/v1/reports/reorder-recommendations` returns prioritized reorder suggestions.

**Priority scoring:**

| Condition | Score Added |
|---|---|
| Status = `critical` | +100 |
| Status = `very_low` | +75 |
| Status = `low` | +50 |
| `days_until_stockout < 7` | +50 |

`days_until_stockout = quantity_available / average_daily_use`

**Low stock report:** `GET /api/v1/reports/low-stock` returns items with status `low`, `very_low`, or `critical`, split into two arrays plus a summary (counts and value at risk).

---

## 9. Data Model Reference

### `purchase_orders`

| Field | Type | Notes |
|---|---|---|
| `po_number` | string (unique) | Auto-generated as `PO-YYYY-####` |
| `supplier_id` | FK | Nullable |
| `status` | enum | `draft`, `submitted`, `approved`, `partially_received`, `received`, `cancelled` |
| `order_date` | date | |
| `expected_date` | date | Nullable |
| `received_date` | date | Nullable |
| `total_amount` | decimal | |
| `ship_to` | string | Nullable |
| `created_by` | FK users | |
| `approved_by` | FK users | Nullable |
| `approved_at` | timestamp | Nullable |

### `purchase_order_items`

| Field | Type | Notes |
|---|---|---|
| `purchase_order_id` | FK | Cascades on delete |
| `product_id` | FK | |
| `quantity_ordered` | integer | |
| `quantity_received` | integer | Default 0 |
| `unit_cost` | decimal | |
| `total_cost` | decimal | Auto-calculated on save |
| `destination_location` | string | Nullable |

### Product replenishment fields

| Field | Type | Notes |
|---|---|---|
| `reorder_point` | integer | Nullable — threshold to trigger reorder |
| `safety_stock` | integer | Nullable — buffer below reorder point |
| `maximum_quantity` | integer | Nullable — order-up-to target |
| `minimum_quantity` | integer | Absolute floor |
| `on_order_qty` | integer | Qty in pending/approved POs |
| `average_daily_use` | decimal | Nullable — for days-until-stockout calc |
| `lead_time_days` | integer | Nullable — supplier lead time |
| `supplier_id` | FK | Primary supplier |
| `supplier_sku` | string | Nullable |
| `pack_size` | integer | Default 1 |
| `min_order_qty` | integer | Nullable |
| `order_multiple` | integer | Nullable |

---

## 10. Key Constraints & Current Behaviour

- Products without a `supplier_id` never appear in the replenishment UI
- POs can only have items added or removed while in `draft`
- `on_order_qty` is tracked at the **product level** (single field), not broken out per PO
- Receipt must be performed manually — there is no auto-receive
- Approval has no permission gate — any authenticated user can approve
- PO number must be entered by the user (auto-generated format is suggested but editable)
