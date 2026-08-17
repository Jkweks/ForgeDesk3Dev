<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    /**
     * The base `inventory.*`/`orders.*`/`maintenance.view` permissions created in
     * 2026_02_09_000002_create_roles_and_permissions_tables.php were only ever
     * assigned to the `admin` role — every later migration adds narrower,
     * feature-specific permissions (orders.submit, cycle-count.*, jobs.*, ...) and
     * assigns those correctly, but nobody ever granted `manager`/`fabricator`/
     * `viewer` the base CRUD permissions that ACTION_PERMISSIONS_GUIDE.md documents
     * them as having (e.g. "Warehouse Manager: inventory.* — Full inventory control,
     * orders.* — Full order management"). That was harmless while these routes had
     * no server-side permission checks at all, but now that `permission:` middleware
     * has been added to the `products`/`categories`/`suppliers`/`storage-locations`/
     * `orders`/`purchase-orders` apiResource routes, it would silently lock these
     * roles out of CRUD they're documented — and expected — to have.
     */
    public function up(): void
    {
        $manager  = Role::where('name', 'manager')->first();
        $fab      = Role::where('name', 'fabricator')->first();
        $viewer   = Role::where('name', 'viewer')->first();

        // Warehouse Manager: full inventory control, full order management,
        // reports view+export, maintenance view.
        if ($manager) {
            foreach ([
                'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete', 'inventory.adjust',
                'orders.view', 'orders.create', 'orders.edit', 'orders.delete',
                'reports.view', 'reports.export',
                'maintenance.view',
            ] as $name) {
                $manager->assignPermission($name);
            }
        }

        // Receiving Clerk (fabricator): view/edit inventory, view/edit orders.
        if ($fab) {
            foreach ([
                'inventory.view', 'inventory.edit',
                'orders.view', 'orders.edit',
            ] as $name) {
                $fab->assignPermission($name);
            }
        }

        // Production Viewer: read-only inventory + reports.
        if ($viewer) {
            foreach ([
                'inventory.view',
                'reports.view',
            ] as $name) {
                $viewer->assignPermission($name);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $manager = Role::where('name', 'manager')->first();
        $fab     = Role::where('name', 'fabricator')->first();
        $viewer  = Role::where('name', 'viewer')->first();

        $managerPerms = [
            'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete', 'inventory.adjust',
            'orders.view', 'orders.create', 'orders.edit', 'orders.delete',
            'reports.view', 'reports.export',
            'maintenance.view',
        ];
        $fabPerms = ['inventory.view', 'inventory.edit', 'orders.view', 'orders.edit'];
        $viewerPerms = ['inventory.view', 'reports.view'];

        if ($manager) {
            $ids = Permission::whereIn('name', $managerPerms)->pluck('id');
            $manager->permissions()->detach($ids);
        }
        if ($fab) {
            $ids = Permission::whereIn('name', $fabPerms)->pluck('id');
            $fab->permissions()->detach($ids);
        }
        if ($viewer) {
            $ids = Permission::whereIn('name', $viewerPerms)->pluck('id');
            $viewer->permissions()->detach($ids);
        }
    }
};
