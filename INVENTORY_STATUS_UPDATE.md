# Inventory Status Calculation Update

## Overview
This document outlines the changes made to the inventory status calculation logic and the new product_type field.

## Changes Made

### 1. **New Inventory Status Logic**

The inventory status is now calculated based on the following rules:

| Status | Condition | Description |
|--------|-----------|-------------|
| **Critical** | `available < 0` | Product is over-committed (negative available quantity) |
| **Low Stock** | `0 <= available < reorder_point` | Available quantity is below the reorder point |
| **In Stock** | `available >= reorder_point` | Available quantity is at or above the reorder point |

**Note:** The `out_of_stock` status has been removed and replaced with the `critical` status for over-committed items.

### 2. **New Product Type Field**

A new `product_type` field has been added to support product lifecycle management:

| Product Type | Description |
|--------------|-------------|
| **active** | Standard active product (default) |
| **inactive** | Temporarily inactive product |
| **special_order** | Special order only (not stocked) |
| **obsolete** | Obsolete product (hidden from main lists when quantity is 0) |

### 3. **Obsolete Product Filtering**

Products marked as `obsolete` with both `quantity_on_hand = 0` AND `quantity_available = 0` are automatically hidden from the main product list via the `visible()` scope.

## Database Changes

### Migration Files Created:

1. **2026_02_13_000001_add_product_type_to_products_table.php**
   - Adds `product_type` enum field with values: active, inactive, special_order, obsolete
   - Default value: 'active'

2. **2026_02_13_000002_update_product_status_enum.php**
   - Updates `status` enum to remove 'out_of_stock'
   - Migrates existing 'out_of_stock' records to 'critical'
   - New enum values: in_stock, low_stock, critical

## Backend API Changes

### Product Model (`app/Models/Product.php`)

**New Methods:**
- `isObsolete()`: Check if product is obsolete
- `shouldBeHidden()`: Check if product should be hidden from display
- `getProductTypeNameAttribute()`: Get formatted product type name
- `scopeVisible($query)`: Query scope to filter out hidden obsolete products

**Updated Methods:**
- `updateStatus()`: New logic based on reorder_point and available quantity

**New Configuration:**
```php
public static $productTypes = [
    'active' => 'Active',
    'inactive' => 'Inactive',
    'special_order' => 'Special Order',
    'obsolete' => 'Obsolete',
];
```

### Product Controller (`app/Http/Controllers/Api/ProductController.php`)

**Changes:**
- Added `product_type` to validation rules in `store()` and `update()` methods
- Added `visible()` scope to `index()` method to automatically filter obsolete products
- Validation: `'product_type' => 'nullable|in:active,inactive,special_order,obsolete'`

### Status Controller (`app/Http/Controllers/Api/StatusController.php`)

**Changes:**
- Removed `out_of_stock` count from inventory stats

## Frontend Integration Required

### 1. Product Form Updates

Add a dropdown/select field for `product_type`:

```javascript
// Example field structure
{
  label: "Product Type",
  field: "product_type",
  type: "select",
  options: [
    { value: "active", label: "Active" },
    { value: "inactive", label: "Inactive" },
    { value: "special_order", label: "Special Order" },
    { value: "obsolete", label: "Obsolete" }
  ],
  default: "active"
}
```

### 2. Status Badge Updates

Update status badge rendering to use the new 3-tier system:

```javascript
// Example badge logic
const getStatusBadge = (status) => {
  switch(status) {
    case 'critical':
      return { color: 'red', label: 'Critical' };
    case 'low_stock':
      return { color: 'yellow', label: 'Low Stock' };
    case 'in_stock':
      return { color: 'green', label: 'In Stock' };
    default:
      return { color: 'gray', label: 'Unknown' };
  }
};
```

### 3. Product Type Badge (Optional)

Add a badge to display product_type:

```javascript
// Example product type badge
const getProductTypeBadge = (productType) => {
  switch(productType) {
    case 'active':
      return { color: 'green', label: 'Active' };
    case 'inactive':
      return { color: 'gray', label: 'Inactive' };
    case 'special_order':
      return { color: 'blue', label: 'Special Order' };
    case 'obsolete':
      return { color: 'red', label: 'Obsolete' };
    default:
      return { color: 'gray', label: 'Active' };
  }
};
```

### 4. Filter Updates

- Remove 'Out of Stock' filter option
- Add filter for product_type
- Update status filters to use: In Stock, Low Stock, Critical

### 5. Dashboard/Stats Updates

Update any dashboard or statistics displays that reference `out_of_stock` to use the new status values.

## Testing Recommendations

1. **Test Status Calculation:**
   - Create a product with reorder_point = 100
   - Set available quantity to 150 → should be "In Stock"
   - Set available quantity to 50 → should be "Low Stock"
   - Set available quantity to -10 → should be "Critical"

2. **Test Product Type:**
   - Create a product with product_type = "obsolete"
   - Set quantity_on_hand = 0 and quantity_available = 0
   - Verify it's hidden from the main product list
   - Verify it's still accessible via direct link/ID

3. **Test Migration:**
   - Verify existing products with status = 'out_of_stock' are migrated to 'critical'
   - Verify new products default to product_type = 'active'

## API Response Changes

### Product List Response

Products now include:
```json
{
  "id": 1,
  "sku": "PROD-001",
  "status": "low_stock",
  "product_type": "active",
  "product_type_name": "Active",
  ...
}
```

### Status Stats Response

Inventory stats no longer include `out_of_stock`:
```json
{
  "inventory": {
    "in_stock": 150,
    "low_stock": 25,
    "critical": 5
  }
}
```

## Breaking Changes

⚠️ **Breaking Changes:**
1. `out_of_stock` status value has been removed
2. Obsolete products with 0 quantity are now hidden by default from product lists
3. API responses no longer include `out_of_stock` in status statistics

## Migration Steps

1. Run migrations: `php artisan migrate`
2. Update frontend code to handle new product_type field
3. Update frontend status badges to remove 'Out of Stock' option
4. Test all product-related functionality
5. Clear any frontend caches

## Support

If you have questions about these changes, please refer to:
- Product Model: `/laravel/app/Models/Product.php:279-295` (updateStatus method)
- Product Controller: `/laravel/app/Http/Controllers/Api/ProductController.php`
- Migration Files: `/laravel/database/migrations/2026_02_13_000001_*.php`
