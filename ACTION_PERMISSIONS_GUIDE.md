# Action-Level Permission System

## Overview

This system provides UI-level access control by hiding/disabling action buttons (Create, Edit, Delete, etc.) based on user permissions. Users without appropriate permissions won't see buttons for actions they can't perform.

## How It Works

### 1. Permission Attributes

Add `data-permission` attributes to any HTML element (buttons, links, forms, etc.) to control visibility:

```html
<!-- Single permission required -->
<button data-permission="inventory.edit">Edit Product</button>

<!-- Any one of multiple permissions (OR logic) -->
<button data-permission-any="inventory.edit,inventory.adjust">Modify Inventory</button>

<!-- All permissions required (AND logic) -->
<button data-permission-all="inventory.view,pricing.view">View Full Details</button>
```

### 2. Automatic Application

Permissions are automatically checked and applied:
- **On page load** - All existing elements checked
- **On dynamic content** - New elements automatically detected via MutationObserver
- **After login** - User permissions refreshed

Elements without permission are:
- Hidden (`display: none`)
- Disabled (for buttons/inputs)
- Removed from tab order (`tabindex: -1`)
- Marked as `aria-hidden="true"` for accessibility

## Available Permissions

Based on your existing permission system (`2026_02_09_000002_create_roles_and_permissions_tables.php`):

### User Management
| Permission | Controls |
|-----------|----------|
| `users.view` | View user list, user details |
| `users.create` | "Add User" button, create forms |
| `users.edit` | "Edit" buttons, update forms |
| `users.delete` | "Delete" buttons |

### Role Management
| Permission | Controls |
|-----------|----------|
| `roles.view` | View roles list, role details |
| `roles.create` | "Add Role" button, create forms |
| `roles.edit` | "Edit" buttons in role cards |
| `roles.delete` | "Delete" options in role dropdowns |

### Inventory Management
| Permission | Controls |
|-----------|----------|
| `inventory.view` | View product details, stock levels |
| `inventory.create` | "Add Product" button, import actions |
| `inventory.edit` | "Edit Product" button, update forms |
| `inventory.delete` | "Delete Product" button |
| `inventory.adjust` | Stock adjustment buttons, quantity changes |

### Orders Management
| Permission | Controls |
|-----------|----------|
| `orders.view` | View order details, order history |
| `orders.create` | "Create Order" button, new PO forms |
| `orders.edit` | "Edit Order" button, order modification |
| `orders.delete` | "Delete Order" button, cancel orders |

### Reports
| Permission | Controls |
|-----------|----------|
| `reports.view` | Access to reports page, report data |
| `reports.export` | "Export" buttons (CSV, PDF, Excel) |

### Maintenance
| Permission | Controls |
|-----------|----------|
| `maintenance.view` | View maintenance records, equipment |
| `maintenance.manage` | Create/edit/delete maintenance records |

### Settings
| Permission | Controls |
|-----------|----------|
| `settings.view` | View system settings |
| `settings.edit` | Modify system settings, save changes |

### Navigation (Already Implemented)
| Permission | Controls |
|-----------|----------|
| `nav.dashboard` | Dashboard navigation item |
| `nav.inventory` | Inventory menu |
| `nav.operations` | Operations menu |
| `nav.fulfillment` | Fulfillment menu |
| `nav.reports` | Reports navigation |
| `nav.maintenance` | Maintenance menu |
| `nav.admin` | Admin panel access |

### Pricing (Special)
| Permission | Controls |
|-----------|----------|
| `pricing.view` | Price visibility (masked if missing) |

## Implementation Examples

### Example 1: Inventory Product Details Page

```html
<!-- Product details card -->
<div class="card">
  <div class="card-header">
    <h3>Product Details</h3>
    <div class="card-actions">
      <!-- Only users with inventory.edit permission see this -->
      <button class="btn btn-primary" onclick="editProduct()" data-permission="inventory.edit">
        <i class="ti ti-edit"></i> Edit
      </button>

      <!-- Only users with inventory.delete permission see this -->
      <button class="btn btn-danger" onclick="deleteProduct()" data-permission="inventory.delete">
        <i class="ti ti-trash"></i> Delete
      </button>

      <!-- Requires BOTH inventory.edit AND inventory.adjust -->
      <button class="btn btn-warning" onclick="adjustStock()" data-permission-all="inventory.edit,inventory.adjust">
        <i class="ti ti-adjustments"></i> Adjust Stock
      </button>
    </div>
  </div>

  <div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <strong>SKU:</strong> ${product.sku}
      </div>

      <!-- Pricing only visible to users with pricing.view -->
      <div class="col-md-6" data-permission="pricing.view">
        <strong>Cost:</strong> <span id="product-cost"></span>
      </div>
    </div>

    <!-- Export button requires reports.export permission -->
    <div class="mt-3" data-permission="reports.export">
      <button class="btn btn-secondary" onclick="exportProductData()">
        <i class="ti ti-download"></i> Export Data
      </button>
    </div>
  </div>
</div>

<script>
// Check permissions in JavaScript if needed
if (canEdit('inventory')) {
  // User can edit inventory
  enableEditMode();
}

if (canDelete('inventory')) {
  // User can delete products
  showDeleteOption();
}

// Use helper functions
if (canViewPricing()) {
  document.getElementById('product-cost').textContent = formatPrice(product.cost);
} else {
  document.getElementById('product-cost').textContent = '−−−−−−';
}
</script>
```

### Example 2: Orders / Purchase Orders Page

```html
<!-- Purchase Orders List -->
<div class="page-header">
  <h1>Purchase Orders</h1>
  <!-- Only users with orders.create see this button -->
  <button class="btn btn-primary" onclick="createPurchaseOrder()" data-permission="orders.create">
    <i class="ti ti-plus"></i> New Purchase Order
  </button>
</div>

<table class="table">
  <thead>
    <tr>
      <th>PO Number</th>
      <th>Supplier</th>
      <th>Status</th>
      <th>Total</th>
      <th data-permission-any="orders.edit,orders.delete">Actions</th>
    </tr>
  </thead>
  <tbody>
    ${orders.map(order => `
      <tr>
        <td>${order.po_number}</td>
        <td>${order.supplier}</td>
        <td>${order.status}</td>
        <td data-permission="pricing.view">${formatPrice(order.total)}</td>
        <td>
          <button class="btn btn-sm btn-icon" onclick="editOrder(${order.id})" data-permission="orders.edit">
            <i class="ti ti-edit"></i>
          </button>
          <button class="btn btn-sm btn-icon text-danger" onclick="deleteOrder(${order.id})" data-permission="orders.delete">
            <i class="ti ti-trash"></i>
          </button>
        </td>
      </tr>
    `).join('')}
  </tbody>
</table>
```

### Example 3: Maintenance Records

```html
<!-- Maintenance Hub -->
<div class="maintenance-header">
  <h1>Maintenance Hub</h1>
  <!-- Only users with maintenance.manage can create records -->
  <button class="btn btn-primary" onclick="createMaintenanceTask()" data-permission="maintenance.manage">
    <i class="ti ti-plus"></i> New Maintenance Task
  </button>
</div>

<!-- Maintenance task card -->
<div class="card">
  <div class="card-body">
    <h4>Replace Hydraulic Filter - Machine #5</h4>
    <p>Due: 2026-02-15</p>

    <!-- Edit/complete buttons only for users with maintenance.manage -->
    <div class="card-actions" data-permission="maintenance.manage">
      <button class="btn btn-success" onclick="completeTask()">
        <i class="ti ti-check"></i> Mark Complete
      </button>
      <button class="btn btn-secondary" onclick="editTask()">
        <i class="ti ti-edit"></i> Edit
      </button>
      <button class="btn btn-danger" onclick="deleteTask()">
        <i class="ti ti-trash"></i> Delete
      </button>
    </div>
  </div>
</div>
```

### Example 4: Reports Page with Export

```html
<!-- Reports Dashboard -->
<div class="reports-container">
  <h1>Inventory Reports</h1>

  <!-- Report viewer visible to all with reports.view -->
  <div class="report-content" data-permission="reports.view">
    <canvas id="inventoryChart"></canvas>
    <table id="reportData">
      <!-- Report data here -->
    </table>
  </div>

  <!-- Export actions only for users with reports.export -->
  <div class="export-actions" data-permission="reports.export">
    <button class="btn btn-primary" onclick="exportToPDF()">
      <i class="ti ti-file-pdf"></i> Export to PDF
    </button>
    <button class="btn btn-secondary" onclick="exportToExcel()">
      <i class="ti ti-file-excel"></i> Export to Excel
    </button>
    <button class="btn btn-outline" onclick="exportToCSV()">
      <i class="ti ti-file-csv"></i> Export to CSV
    </button>
  </div>
</div>
```

### Example 5: Settings Page

```html
<!-- System Settings -->
<div class="settings-page">
  <h1>System Settings</h1>

  <!-- Anyone with settings.view can see settings -->
  <div class="settings-form" data-permission="settings.view">
    <div class="form-group">
      <label>Company Name</label>
      <input type="text" id="companyName" value="ForgeDesk" disabled>
    </div>

    <div class="form-group">
      <label>Low Stock Threshold</label>
      <input type="number" id="lowStockThreshold" value="10" disabled>
    </div>

    <!-- Save button only visible to users with settings.edit -->
    <div class="form-actions" data-permission="settings.edit">
      <button class="btn btn-primary" onclick="saveSettings()">
        <i class="ti ti-save"></i> Save Changes
      </button>
    </div>
  </div>
</div>

<script>
// Enable form fields for users with edit permission
if (canEdit('settings')) {
  document.getElementById('companyName').disabled = false;
  document.getElementById('lowStockThreshold').disabled = false;
}
</script>
```

## JavaScript Helper Functions

Use these helper functions in your JavaScript code:

```javascript
// Check specific permission
if (hasPermission('inventory.edit')) {
  // User can edit inventory
}

// Convenient shortcuts
if (canCreate('inventory')) {
  // User can create inventory items
}

if (canEdit('orders')) {
  // User can edit orders
}

if (canDelete('users')) {
  // User can delete users
}

if (canView('reports')) {
  // User can view reports
}

if (canManage('maintenance')) {
  // User can manage maintenance
}

if (canAdjust('inventory')) {
  // User can adjust inventory quantities
}

if (canExport('reports')) {
  // User can export reports
}

// Special pricing check
if (canViewPricing()) {
  // Show real prices
} else {
  // Mask prices
}
```

## Adding Permissions to New Pages

When creating a new page or feature:

### Step 1: Identify Actions

List all actions users can take:
- View data
- Create new records
- Edit existing records
- Delete records
- Export data
- Special actions (approve, adjust, manage, etc.)

### Step 2: Map to Permissions

Use existing permissions or create new ones following the pattern:
- `{resource}.view` - View/read access
- `{resource}.create` - Create new
- `{resource}.edit` - Modify existing
- `{resource}.delete` - Remove
- `{resource}.{action}` - Special actions

### Step 3: Add Attributes

Add `data-permission` attributes to all action elements:

```html
<!-- Create button -->
<button data-permission="resource.create">Create</button>

<!-- Edit button -->
<button data-permission="resource.edit">Edit</button>

<!-- Delete button -->
<button data-permission="resource.delete">Delete</button>

<!-- Combined action -->
<button data-permission-all="resource.edit,resource.special">Advanced Edit</button>
```

### Step 4: Test

Test with different roles:
1. Create test role with limited permissions
2. Assign test user to that role
3. Log in as test user
4. Verify buttons show/hide correctly

## Dynamic Content

For content loaded via AJAX or dynamically created:

```javascript
// After creating dynamic content with permission attributes
const newButton = document.createElement('button');
newButton.setAttribute('data-permission', 'inventory.edit');
newButton.textContent = 'Edit';
document.body.appendChild(newButton);

// Permissions are applied automatically via MutationObserver
// No need to manually call applyActionPermissions()

// But if you need to force reapply:
applyActionPermissions();
```

## Troubleshooting

### Buttons Not Hiding

1. **Check attribute spelling**: Must be exactly `data-permission`, `data-permission-any`, or `data-permission-all`
2. **Verify permission name**: Must match database exactly (case-sensitive)
3. **Check user permissions**: Use browser console:
   ```javascript
   console.log(currentUser.permissions);
   hasPermission('inventory.edit'); // Returns true/false
   ```
4. **Refresh login**: Logout and login again to refresh permissions

### Buttons Still Clickable

If buttons are hidden but still accessible:
- Clear browser cache
- Check for JavaScript errors in console
- Verify `applyActionPermissions()` is being called

### Dynamic Content Not Working

If dynamically added buttons don't get permissions applied:
- Check browser console for errors
- Verify MutationObserver is active: `initPermissionWatcher()` should run on page load
- Manually call `applyActionPermissions()` after creating content

## Security Important Notes

⚠️ **Critical Security Information**

1. **UI Hiding is NOT Security**: Buttons are hidden via CSS and can be re-enabled in browser DevTools
2. **Always Protect Backend**: Every API endpoint MUST check permissions server-side
3. **Defense in Depth**: Use both frontend (UX) and backend (security) checks

### Backend Protection Example

```php
// In your controller
public function update(Request $request, $id)
{
    // REQUIRED: Check permission server-side
    if (!auth()->user()->hasPermission('inventory.edit')) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    // Proceed with update...
}

// Using middleware
Route::middleware(['auth:sanctum', 'permission:inventory.edit'])
    ->put('/products/{id}', [ProductController::class, 'update']);
```

## Role Permission Assignment

To assign permissions when creating roles:

### Via Admin Panel

1. Go to **Admin Panel > Permissions & Roles**
2. Click **"Add Role"** or **"Edit"** existing role
3. Under **Permissions**, find categories:
   - **Users** - users.view, users.create, users.edit, users.delete
   - **Roles** - roles.view, roles.create, roles.edit, roles.delete
   - **Inventory** - inventory.view, inventory.create, inventory.edit, inventory.delete, inventory.adjust
   - **Orders** - orders.view, orders.create, orders.edit, orders.delete
   - **Reports** - reports.view, reports.export
   - **Maintenance** - maintenance.view, maintenance.manage
   - **Settings** - settings.view, settings.edit
   - **Navigation** - nav.dashboard, nav.inventory, etc.
   - **Pricing** - pricing.view
4. Check permissions needed for the role
5. Click **"Save"**

### Example Role Configurations

**Receiving Clerk**:
- ✅ `nav.dashboard` - See dashboard
- ✅ `nav.inventory` - Access inventory menu
- ✅ `nav.operations` - Access purchase orders
- ✅ `inventory.view` - View products
- ✅ `inventory.edit` - Update received items
- ✅ `orders.view` - View purchase orders
- ✅ `orders.edit` - Mark items as received
- ❌ All other permissions

**Warehouse Manager**:
- ✅ All navigation except `nav.admin`
- ✅ `inventory.*` - Full inventory control
- ✅ `orders.*` - Full order management
- ✅ `reports.view` + `reports.export` - View and export reports
- ✅ `maintenance.view` - View equipment status
- ❌ `users.*`, `roles.*`, `settings.*` - No admin functions

**Production Viewer**:
- ✅ `nav.dashboard`, `nav.inventory`, `nav.reports` - Limited navigation
- ✅ `inventory.view` - View inventory only
- ✅ `reports.view` - View reports only
- ❌ All create/edit/delete permissions

## Testing Checklist

After implementing permissions:

- [ ] Admin role can see all buttons
- [ ] Viewer role sees no edit/delete buttons
- [ ] Custom role with limited permissions hides appropriate buttons
- [ ] Dynamically loaded content respects permissions
- [ ] Modal forms apply permissions correctly
- [ ] Browser console shows no permission-related errors
- [ ] Backend endpoints reject unauthorized requests (403)
- [ ] Navigation hides/shows correctly based on nav permissions
- [ ] Pricing masks correctly for users without pricing.view
- [ ] Buttons are disabled AND hidden (not just hidden)
- [ ] Keyboard navigation skips hidden buttons (tabindex)
- [ ] Screen readers don't announce hidden buttons (aria-hidden)

---

**Last Updated**: 2026-02-11
**Version**: 1.0.0
**Related**: See also `NAVIGATION_PERMISSIONS.md` for navigation-level controls
