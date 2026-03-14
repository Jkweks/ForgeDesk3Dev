<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Define navigation permissions
        $navPermissions = [
            [
                'name' => 'nav.dashboard',
                'display_name' => 'Dashboard Navigation',
                'description' => 'Access to Dashboard navigation item',
                'category' => 'navigation'
            ],
            [
                'name' => 'nav.inventory',
                'display_name' => 'Inventory Navigation',
                'description' => 'Access to Inventory navigation menu',
                'category' => 'navigation'
            ],
            [
                'name' => 'nav.operations',
                'display_name' => 'Operations Navigation',
                'description' => 'Access to Operations navigation menu (Purchase Orders, Cycle Counting, Storage Locations)',
                'category' => 'navigation'
            ],
            [
                'name' => 'nav.fulfillment',
                'display_name' => 'Fulfillment Navigation',
                'description' => 'Access to Fulfillment navigation menu (Material Check, Job Reservations)',
                'category' => 'navigation'
            ],
            [
                'name' => 'nav.reports',
                'display_name' => 'Reports Navigation',
                'description' => 'Access to Reports navigation item',
                'category' => 'navigation'
            ],
            [
                'name' => 'nav.maintenance',
                'display_name' => 'Maintenance Navigation',
                'description' => 'Access to Maintenance navigation menu',
                'category' => 'navigation'
            ],
            [
                'name' => 'nav.admin',
                'display_name' => 'Admin Navigation',
                'description' => 'Access to Admin navigation item',
                'category' => 'navigation'
            ],
        ];

        // Create permissions
        foreach ($navPermissions as $permission) {
            Permission::create($permission);
        }

        // Assign navigation permissions to roles
        $this->assignNavigationPermissions();
    }

    /**
     * Assign navigation permissions to existing roles
     */
    private function assignNavigationPermissions(): void
    {
        // Admin - Full access to all navigation
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->assignPermission('nav.dashboard');
            $adminRole->assignPermission('nav.inventory');
            $adminRole->assignPermission('nav.operations');
            $adminRole->assignPermission('nav.fulfillment');
            $adminRole->assignPermission('nav.reports');
            $adminRole->assignPermission('nav.maintenance');
            $adminRole->assignPermission('nav.admin');
        }

        // Manager - All except Admin panel
        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole) {
            $managerRole->assignPermission('nav.dashboard');
            $managerRole->assignPermission('nav.inventory');
            $managerRole->assignPermission('nav.operations');
            $managerRole->assignPermission('nav.fulfillment');
            $managerRole->assignPermission('nav.reports');
            $managerRole->assignPermission('nav.maintenance');
            // No nav.admin for managers
        }

        // Fabricator - Operations focused (no Maintenance, no Admin)
        $fabricatorRole = Role::where('name', 'fabricator')->first();
        if ($fabricatorRole) {
            $fabricatorRole->assignPermission('nav.dashboard');
            $fabricatorRole->assignPermission('nav.inventory');
            $fabricatorRole->assignPermission('nav.operations');
            $fabricatorRole->assignPermission('nav.fulfillment');
            $fabricatorRole->assignPermission('nav.reports');
            // No nav.maintenance
            // No nav.admin
        }

        // Viewer - Read-only sections (Dashboard, Inventory, Reports only)
        $viewerRole = Role::where('name', 'viewer')->first();
        if ($viewerRole) {
            $viewerRole->assignPermission('nav.dashboard');
            $viewerRole->assignPermission('nav.inventory');
            $viewerRole->assignPermission('nav.reports');
            // No nav.operations
            // No nav.fulfillment
            // No nav.maintenance
            // No nav.admin
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove navigation permissions
        Permission::where('category', 'navigation')->delete();
    }
};
