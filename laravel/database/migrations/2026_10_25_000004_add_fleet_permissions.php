<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Field & fleet assets:
 *  - fleet.view              — see assets, history, documents (admin, manager, office_staff)
 *  - fleet.manage            — create/edit assets, plans, work orders, inspections, compliance (admin, manager)
 *  - fleet.documents.manage  — upload/delete documents (admin, manager, office_staff)
 *  - fleet.delete            — delete/retire assets (admin)
 *  - fleet.costs.view        — purchase + maintenance cost figures (admin, manager)
 *  - nav.fleet               — navigation entry (admin, manager, office_staff)
 * Field crews have no ForgeDesk accounts; they use the separate field app.
 */
return new class extends Migration
{
    private const PERMS = [
        'fleet.view' => ['Fleet: View', 'View field/fleet assets, history and documents', ['admin', 'manager', 'office_staff']],
        'fleet.manage' => ['Fleet: Manage', 'Create and edit assets, maintenance plans, work orders, inspections and compliance', ['admin', 'manager']],
        'fleet.documents.manage' => ['Fleet: Manage Documents', 'Upload and delete fleet asset documents', ['admin', 'manager', 'office_staff']],
        'fleet.delete' => ['Fleet: Delete', 'Delete or retire field/fleet assets', ['admin']],
        'fleet.costs.view' => ['Fleet: View Costs', 'See purchase and maintenance costs', ['admin', 'manager']],
        'nav.fleet' => ['Navigation: Fleet', 'See the Field & Fleet menu in the navigation bar', ['admin', 'manager', 'office_staff']],
    ];

    public function up(): void
    {
        foreach (self::PERMS as $name => [$display, $desc, $roles]) {
            Permission::firstOrCreate(['name' => $name], [
                'display_name' => $display, 'description' => $desc, 'category' => 'fleet',
            ]);
            foreach ($roles as $role) {
                Role::where('name', $role)->first()?->assignPermission($name);
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::PERMS) as $name) {
            $perm = Permission::where('name', $name)->first();
            if ($perm) {
                $perm->roles()->detach();
                $perm->delete();
            }
        }
    }
};
