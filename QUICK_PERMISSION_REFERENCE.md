# Quick Permission Reference Card

## 🔒 How to Control Button Visibility

### Single Permission
```html
<button data-permission="inventory.edit">Edit Product</button>
```
Button shows only if user has `inventory.edit` permission.

### Any Permission (OR)
```html
<button data-permission-any="inventory.edit,inventory.adjust">Modify Inventory</button>
```
Button shows if user has **either** `inventory.edit` OR `inventory.adjust`.

### All Permissions (AND)
```html
<button data-permission-all="inventory.edit,pricing.view">Edit with Pricing</button>
```
Button shows only if user has **both** `inventory.edit` AND `pricing.view`.

---

## 📋 Permission Quick List

### Inventory Actions
| Action | Permission | Use For |
|--------|-----------|---------|
| View products | `inventory.view` | Product details, stock view |
| Add products | `inventory.create` | "Add Product" button |
| Edit products | `inventory.edit` | "Edit" button, update forms |
| Delete products | `inventory.delete` | "Delete" button |
| Adjust stock | `inventory.adjust` | Stock quantity changes |

### Order Actions
| Action | Permission | Use For |
|--------|-----------|---------|
| View orders | `orders.view` | Order list, order details |
| Create orders | `orders.create` | "New PO" button |
| Edit orders | `orders.edit` | "Edit Order", mark received |
| Delete orders | `orders.delete` | "Delete Order" button |

### User Management
| Action | Permission | Use For |
|--------|-----------|---------|
| View users | `users.view` | User list |
| Add users | `users.create` | "Add User" button |
| Edit users | `users.edit` | "Edit User" button |
| Delete users | `users.delete` | "Delete User" button |

### Other Common Actions
| Action | Permission | Use For |
|--------|-----------|---------|
| View reports | `reports.view` | Reports page |
| Export reports | `reports.export` | Export buttons (PDF/Excel) |
| View pricing | `pricing.view` | Show real prices vs masked |
| Manage maintenance | `maintenance.manage` | Create/edit maintenance tasks |
| Edit settings | `settings.edit` | "Save Settings" button |

---

## 🚀 Quick Implementation Steps

### Step 1: Find the Button
Locate the button/link you want to control in your HTML.

### Step 2: Add Permission Attribute
Add `data-permission="resource.action"` to the element:
```html
<!-- Before -->
<button onclick="deleteProduct()">Delete</button>

<!-- After -->
<button onclick="deleteProduct()" data-permission="inventory.delete">Delete</button>
```

### Step 3: Test
- Login as user without the permission → Button should be hidden
- Login as user with the permission → Button should be visible

---

## 🧪 Testing Permissions

### In Browser Console
```javascript
// Check if current user has a permission
hasPermission('inventory.edit')  // true or false

// View all user's permissions
console.log(currentUser.permissions)

// Use helper functions
canEdit('inventory')     // true or false
canCreate('orders')      // true or false
canDelete('users')       // true or false
```

### Create Test Roles
1. Admin Panel → Permissions & Roles
2. Create "Test Viewer" role with only `.view` permissions
3. Create test user assigned to "Test Viewer"
4. Login as test user
5. Verify no edit/delete buttons visible

---

## 💡 Common Patterns

### Product Details Page
```html
<div class="product-actions">
  <!-- Edit button - requires inventory.edit -->
  <button data-permission="inventory.edit">Edit</button>

  <!-- Delete button - requires inventory.delete -->
  <button data-permission="inventory.delete">Delete</button>

  <!-- Adjust stock - requires inventory.adjust -->
  <button data-permission="inventory.adjust">Adjust Stock</button>
</div>

<!-- Pricing section - only visible with pricing.view -->
<div class="pricing-info" data-permission="pricing.view">
  <strong>Cost:</strong> $99.99
</div>
```

### Order List Page
```html
<!-- Create button -->
<button data-permission="orders.create">New Purchase Order</button>

<!-- Actions column in table -->
<td>
  <button data-permission="orders.edit">Edit</button>
  <button data-permission="orders.delete">Delete</button>
</td>

<!-- Export button -->
<button data-permission="reports.export">Export to PDF</button>
```

### Maintenance Page
```html
<!-- Create task button -->
<button data-permission="maintenance.manage">New Maintenance Task</button>

<!-- Task actions - requires manage permission -->
<div data-permission="maintenance.manage">
  <button>Complete Task</button>
  <button>Edit Task</button>
  <button>Delete Task</button>
</div>
```

---

## ⚠️ Important Security Notes

1. **UI hiding is NOT security** - Buttons can be re-enabled in DevTools
2. **Always protect backend** - Check permissions in your Laravel controllers:
   ```php
   if (!auth()->user()->hasPermission('inventory.edit')) {
       abort(403, 'Unauthorized');
   }
   ```
3. **Use both layers** - Frontend (UX) + Backend (security)

---

## 📚 Full Documentation

See `ACTION_PERMISSIONS_GUIDE.md` for:
- Complete permission list
- Detailed examples for all areas
- How to add permissions to new pages
- JavaScript API reference
- Troubleshooting guide

---

**Quick Start**: Just add `data-permission="resource.action"` to any button! 🎉
