# Role-Based Navigation Permissions

## Overview

This system implements granular control over navigation menu visibility based on user roles and permissions. Each navigation item can be independently controlled through permissions, allowing you to create custom roles with specific navigation access.

## Navigation Permissions

The following navigation permissions have been added to the system:

| Permission | Display Name | Description |
|-----------|-------------|-------------|
| `nav.dashboard` | Dashboard Navigation | Access to Dashboard navigation item |
| `nav.inventory` | Inventory Navigation | Access to Inventory navigation menu |
| `nav.operations` | Operations Navigation | Access to Operations menu (Purchase Orders, Cycle Counting, Storage Locations) |
| `nav.fulfillment` | Fulfillment Navigation | Access to Fulfillment menu (Material Check, Job Reservations) |
| `nav.reports` | Reports Navigation | Access to Reports navigation item |
| `nav.maintenance` | Maintenance Navigation | Access to Maintenance navigation menu |
| `nav.admin` | Admin Navigation | Access to Admin navigation item |

## Default Role Assignments

Navigation permissions are automatically assigned to the default system roles as follows:

### Admin Role
- ✅ Dashboard
- ✅ Inventory
- ✅ Operations
- ✅ Fulfillment
- ✅ Reports
- ✅ Maintenance
- ✅ Admin

**Full access to all navigation items**

### Manager Role
- ✅ Dashboard
- ✅ Inventory
- ✅ Operations
- ✅ Fulfillment
- ✅ Reports
- ✅ Maintenance
- ❌ Admin

**All navigation except Admin panel**

### Fabricator Role
- ✅ Dashboard
- ✅ Inventory
- ✅ Operations
- ✅ Fulfillment
- ✅ Reports
- ❌ Maintenance
- ❌ Admin

**Operations-focused access (no Maintenance or Admin)**

### Viewer Role
- ✅ Dashboard
- ✅ Inventory
- ✅ Reports
- ❌ Operations
- ❌ Fulfillment
- ❌ Maintenance
- ❌ Admin

**Read-only sections (Dashboard, Inventory, Reports only)**

## Creating Custom Roles

You can create custom roles with specific navigation permissions through the Admin Panel:

1. Navigate to **Admin Panel > Permissions & Roles** tab
2. Click **"Add Role"**
3. Enter role details:
   - **Role Name**: Internal identifier (lowercase, underscores, e.g., `receiving_clerk`)
   - **Display Name**: User-friendly name (e.g., `Receiving Clerk`)
   - **Description**: Brief description of the role
4. Under **Permissions**, expand the **Navigation** category
5. Check the navigation items this role should access
6. Add any additional functional permissions (inventory, orders, reports, etc.)
7. Click **"Create Role"**

### Example: Receiving Clerk Role

A receiving clerk might need:
- ✅ `nav.dashboard` - View dashboard
- ✅ `nav.inventory` - Access inventory to receive shipments
- ✅ `nav.operations` - Access purchase orders to mark items received
- ❌ `nav.fulfillment` - No need for fulfillment tasks
- ❌ `nav.reports` - No reporting access
- ❌ `nav.maintenance` - No maintenance access
- ❌ `nav.admin` - No admin access

Plus functional permissions like:
- `inventory.view`
- `inventory.edit` (for receiving)
- `orders.view`
- `orders.edit` (for marking received)

## How It Works

### Technical Implementation

1. **Database**: Navigation permissions are stored in the `permissions` table with category `'navigation'`
2. **Role Assignment**: Permissions are linked to roles via the `role_permissions` pivot table
3. **Frontend Rendering**:
   - Each navigation item has a `data-nav-permission` attribute
   - JavaScript checks user permissions on page load
   - Navigation items without permission are hidden via `display: none`

### Permission Checking Flow

```
User Login
    ↓
API returns user data with permissions array
    ↓
Frontend stores permissions in localStorage
    ↓
Page loads → applyNavigationPermissions() runs
    ↓
Each nav item checked against user permissions
    ↓
Items without permission are hidden
```

### Code Locations

- **Migration**: `laravel/database/migrations/2026_02_11_000001_add_navigation_permissions.php`
- **Navigation Template**: `laravel/resources/views/partials/navigation.blade.php`
- **Permission Handler**: `laravel/resources/views/partials/auth-scripts.blade.php` (function `applyNavigationPermissions()`)
- **Admin Panel**: `laravel/resources/views/admin.blade.php`

## Modifying Navigation Permissions

### For Existing Roles

1. Go to **Admin Panel > Permissions & Roles**
2. Click the **"Edit"** button on the role card
3. Scroll to **Permissions** section
4. Find the **Navigation** category
5. Check/uncheck navigation permissions as needed
6. Click **"Save Changes"**

Users with that role will see updated navigation immediately after logging out and back in.

### For New Users

When creating a user in the Admin Panel:
1. The **Role** dropdown now includes all custom roles
2. Select the appropriate role for the user
3. Navigation permissions are automatically inherited from the selected role

## Testing Navigation Permissions

To test navigation visibility:

1. Create a test role with specific navigation permissions
2. Create a test user assigned to that role
3. Log out and log in as the test user
4. Verify that only authorized navigation items appear
5. Attempt to access restricted pages directly (type URL) - ensure proper backend protection

## Security Considerations

⚠️ **Important**: Navigation hiding is a UX feature, not a security feature.

- Navigation items are hidden using CSS (`display: none`)
- Users can still attempt to access pages by typing URLs directly
- **Always implement server-side authorization** on Laravel routes and controllers
- Use middleware and permission gates to protect backend endpoints

### Backend Protection Example

```php
// In your route file
Route::middleware(['auth:sanctum', 'permission:maintenance.view'])->group(function () {
    Route::get('/maintenance', [MaintenanceController::class, 'index']);
});

// In your controller
public function index()
{
    if (!auth()->user()->hasPermission('maintenance.view')) {
        abort(403, 'Unauthorized access');
    }

    return view('maintenance');
}
```

## Migration Instructions

When deploying these changes:

1. Pull the latest code
2. Run migrations:
   ```bash
   php artisan migrate
   ```
3. The migration will:
   - Create 7 new navigation permissions
   - Automatically assign permissions to existing roles
   - Preserve all existing data

## Troubleshooting

### Navigation items not hiding

1. **Check browser console** for JavaScript errors
2. **Verify user permissions** in localStorage:
   ```javascript
   console.log(JSON.parse(localStorage.getItem('userData')).permissions)
   ```
3. **Clear cache** and refresh the page
4. **Re-login** to refresh user data

### Custom roles not appearing in dropdowns

1. **Verify migration ran** successfully
2. **Check Admin Panel** - go to Permissions & Roles tab
3. **Refresh the page** - roles are loaded on page load
4. If still not appearing, check browser console for API errors

### User can see navigation but gets 403 on page

This is expected behavior. Navigation visibility and page access are separate:
- Navigation permissions control what users see in the menu
- Page permissions control what users can actually access
- Ensure both are properly configured for each role

## Future Enhancements

Potential improvements to consider:

1. **Sub-menu permissions**: Granular control over dropdown items (e.g., show Inventory but hide Suppliers)
2. **Dynamic navigation**: Fetch navigation structure from backend instead of static HTML
3. **Permission-based routing**: Automatically redirect unauthorized users
4. **Audit logging**: Track when users attempt to access restricted areas
5. **Role templates**: Pre-configured role templates for common use cases

## Support

For questions or issues related to navigation permissions:
1. Check this documentation
2. Review the migration file for permission definitions
3. Inspect the JavaScript console for errors
4. Contact the development team

---

**Last Updated**: 2026-02-11
**Version**: 1.0.0
