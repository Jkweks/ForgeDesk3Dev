# Cycle Count Workflow

## Overview

The cycle count workflow enables physical inventory verification by creating counting sessions scoped to specific storage locations or product categories. Counters record physical quantities, variances are reviewed and approved, and inventory is adjusted automatically with a full audit trail.

---

## 1. Session Setup

A cycle count session (`CycleCountSession`) defines the scope and schedule of a count.

**Scope options (mix-and-match):**
- **Storage locations** — one or more locations; child locations are automatically included (hierarchy-aware)
- **Category** — filter products by category
- **Specific products** — override location/category filters with an explicit product list

**Item generation on creation:**
- If scoped by storage location: one `CycleCountItem` is created per product-location pair that has stock at those locations
- If not scoped by location: one item is created per matching product (product-level, no location)
- `system_quantity` is captured at creation time from `InventoryLocation.quantity` (or `Product.quantity_on_hand` for legacy items) and converted to packs if `product.pack_size > 1`

**Session numbering:** Auto-generated as `CC-YYYYMMDD-NNN` (unique per day).

---

## 2. Session Status Lifecycle

```
planned → in_progress → completed
    ↓           ↓
  cancel      cancel
```

| Transition | Trigger | Notes |
|---|---|---|
| `planned → in_progress` | `POST .../start` or first count recorded | Sets `started_at` |
| `in_progress → completed` | `POST .../complete` | All items must be counted; all non-zero variances must be approved |
| Any → `cancelled` | `POST .../cancel` | Cannot cancel a completed session |

---

## 3. Count Entry

Counting can be done two ways:

### List View
Tabular display of all items in the session. Fields per row:
- SKU, description, location, system qty, counted qty (editable input), variance (live-calculated), status badge, notes

### Guided View (iPad-optimised)
Full-screen, one product at a time:
- Large count input with unit label (packs or eaches)
- System quantity shown for reference
- Enter key or "Save & Next" advances to next uncounted item
- Navigation: Prev / Skip / Next
- Collapsible full item list with counted indicators

Both views call `POST /api/v1/cycle-counts/{id}/record-count` per item save.

**Request body:**
```json
{
  "item_id": 123,
  "counted_quantity": 4,
  "notes": "Optional notes"
}
```

- If the session is still `planned` when the first count is recorded, it auto-transitions to `in_progress`
- `counted_quantity` is in the product's counting unit (packs or eaches)

---

## 4. Variance Calculation & Classification

```
variance = counted_quantity - system_quantity
```

- **Positive** variance: more stock found than system expects
- **Negative** variance: less stock found
- **Zero** variance: exact match

**Variance status assigned on count record:**

| Condition | Status |
|---|---|
| `variance == 0` | `within_tolerance` |
| `abs(variance) <= 5` | `within_tolerance` |
| `abs(variance) > 5` | `requires_review` |

> The threshold of 5 applies to all items regardless of pack size at the `recordCount` stage. The `needs_review` computed attribute uses a pack-aware threshold (1 pack for pack items, 5 eaches for each items) for UI display purposes.

---

## 5. Variance Review & Approval

The variance review modal shows all items where `variance != 0`, including items already approved. Items within tolerance can be approved in bulk alongside items requiring review.

**Approve selected variances:**
```
POST /api/v1/cycle-counts/{id}/approve-variances
{ "item_ids": [123, 124, ...] }
```

**For each approved item (`CycleCountItem::approveVariance()`):**

**Location-based items (primary path):**
1. `InventoryLocation.quantity` is updated to `counted_quantity_eaches`
2. `Product::recalculateQuantitiesFromLocations()` is called — sums all location quantities and updates `product.quantity_on_hand`, `product.quantity_committed`, and `product.status`
3. An `InventoryTransaction` is created (see below)
4. Item is marked `adjustment_created = true`, `variance_status = 'approved'`

**Product-level items (legacy fallback):**
1. `product.quantity_on_hand` is updated directly to `counted_quantity_eaches`
2. An `InventoryTransaction` is created
3. Item is marked `adjustment_created = true`, `variance_status = 'approved'`

**InventoryTransaction fields for cycle count adjustments:**

| Field | Value |
|---|---|
| `type` | `cycle_count` |
| `quantity` | Delta change in `quantity_on_hand` |
| `quantity_before` / `quantity_after` | Captured before/after |
| `reference_type` | `cycle_count` |
| `reference_number` | Session number (e.g. `CC-20260115-001`) |
| `reference_id` | `session.id` |
| `user_id` | User who approved |
| `notes` | Human-readable description including location and pack info |

---

## 6. Completing a Session

`POST /api/v1/cycle-counts/{id}/complete`

**Preconditions (both must pass):**
1. All items have a `counted_quantity` (no uncounted items)
2. All items with `variance != 0` have a `variance_status != 'pending'` (reviewed/approved)

On completion: `status = 'completed'`, `completed_at = now()`, `reviewed_by = current user`.

---

## 7. Pack Size & Unit Conversion

All quantities in the database (at the `InventoryLocation` level) are stored in **eaches**.

For products with `pack_size > 1`:

| Step | Detail |
|---|---|
| `system_quantity` (stored on item) | Converted to full packs at session creation: `floor(eaches / pack_size)` |
| Count entry | User enters pack count |
| Variance | Calculated in packs |
| Approval | Converts back: `counted_packs × pack_size` → updates location in eaches |

Example: 100 eaches on shelf, `pack_size = 20` → system shows 5 packs. Counter counts 4 packs → variance = −1 pack → approval sets location to 80 eaches.

---

## 8. API Endpoints

All routes require `auth:sanctum` and are prefixed `/api/v1/`.

| Method | Route | Description |
|---|---|---|
| `GET` | `/cycle-counts` | List sessions — filterable by `status`, `location`, `date_from`, `date_to`, `days_ago`, `search` |
| `POST` | `/cycle-counts` | Create session and generate items |
| `GET` | `/cycle-counts/{id}` | Get session with full item details |
| `PUT` | `/cycle-counts/{id}` | Update session (standard REST) |
| `DELETE` | `/cycle-counts/{id}` | Delete session |
| `POST` | `/cycle-counts/{id}/start` | `planned → in_progress` |
| `POST` | `/cycle-counts/{id}/record-count` | Record a count for one item |
| `POST` | `/cycle-counts/{id}/approve-variances` | Approve selected items and create adjustments |
| `POST` | `/cycle-counts/{id}/complete` | `in_progress → completed` |
| `POST` | `/cycle-counts/{id}/cancel` | Cancel session |
| `GET` | `/cycle-counts/{id}/variance-report` | Variance summary and per-item detail |
| `GET` | `/cycle-counts/{id}/pdf` | Download PDF report |
| `GET` | `/cycle-counts-active` | All active sessions (`planned` or `in_progress`) |
| `GET` | `/cycle-counts-statistics` | System-wide counts and accuracy stats |

---

## 9. Variance Report

`GET /api/v1/cycle-counts/{id}/variance-report` returns:

```json
{
  "session": { ... },
  "variances": [ ...items where variance != 0... ],
  "summary": {
    "total_items_counted": 0,
    "items_with_variance": 0,
    "total_variance": 0,
    "accuracy_percentage": 0,
    "positive_variance": 0,
    "negative_variance": 0,
    "adjustments_created": 0
  }
}
```

---

## 10. PDF Report

`GET /api/v1/cycle-counts/{id}/pdf` generates a downloadable report including:

- Session header: number, status, category, location, dates, assigned user, reviewer
- Summary: total items, counted, variances, accuracy %, progress %
- Items table: SKU, name, location, system qty, counted qty, variance, variance %, committed qty, counter, notes
- Committed quantities sourced from active job reservations

---

## 11. Data Model Reference

### `cycle_count_sessions`

| Field | Type | Notes |
|---|---|---|
| `session_number` | string (unique) | Auto-generated `CC-YYYYMMDD-NNN` |
| `location` | string | Nullable free-text label |
| `storage_location_ids` | JSON | Nullable array of selected location IDs |
| `category_id` | FK | Nullable |
| `status` | enum | `planned`, `in_progress`, `completed`, `cancelled` |
| `scheduled_date` | date | |
| `started_at` | timestamp | Nullable |
| `completed_at` | timestamp | Nullable |
| `assigned_to` | FK users | Nullable |
| `reviewed_by` | FK users | Set on complete |
| `notes` | text | Nullable |

**Computed properties:**

| Property | Formula |
|---|---|
| `total_items` | Count of items in session |
| `counted_items` | Items where `counted_quantity IS NOT NULL` |
| `variance_items` | Items where `variance != 0` |
| `accuracy_percentage` | `(items_with_zero_variance / total_items) × 100` |
| `progress_percentage` | `(counted_items / total_items) × 100` |

### `cycle_count_items`

| Field | Type | Notes |
|---|---|---|
| `session_id` | FK | Cascades on delete |
| `product_id` | FK | |
| `location_id` | FK inventory_locations | Nullable |
| `system_quantity` | integer | In packs or eaches depending on product |
| `counted_quantity` | integer | Nullable until counted |
| `variance` | integer | `counted_quantity - system_quantity` |
| `variance_status` | enum | `pending`, `within_tolerance`, `requires_review`, `approved`, `rejected` |
| `count_notes` | text | Nullable |
| `counted_by` | FK users | Nullable |
| `counted_at` | timestamp | Nullable |
| `adjustment_created` | boolean | True once inventory adjustment applied |
| `transaction_id` | FK inventory_transactions | Nullable |

---

## 12. Key Constraints & Current Behaviour

- `system_quantity` is a **snapshot** taken at session creation — it does not update if stock changes while the session is open
- Sessions can only be completed if every item is counted and every non-zero variance has been actioned
- Variance approval directly modifies `InventoryLocation.quantity` — there is no hold or pending state for the inventory record
- There is no permission gate on variance approval — any authenticated user can approve
- Cancelling a session does **not** roll back any adjustments that were already approved before cancellation
- The legacy (product-level, no location) path exists for backward compatibility; new sessions using storage location filters always create location-scoped items
