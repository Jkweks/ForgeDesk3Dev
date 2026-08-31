<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The `/reports/*` API routes previously had no server-side permission
     * check, so any authenticated user could load them. They are now gated
     * behind `reports.view` (and `reports.export` for exports). The fabricator
     * role has had the `nav.reports` menu item since 2026_02_11 but was never
     * granted `reports.view`, which would leave fabricators looking at a
     * Reports menu whose every call 403s. Grant the view permission to match
     * the navigation they already have. Export stays manager/admin only.
     */
    public function up(): void
    {
        $fabricator = Role::where('name', 'fabricator')->first();

        if ($fabricator && Permission::where('name', 'reports.view')->exists()) {
            $fabricator->assignPermission('reports.view');
        }
    }

    public function down(): void
    {
        $fabricator = Role::where('name', 'fabricator')->first();

        if ($fabricator) {
            $id = Permission::where('name', 'reports.view')->value('id');
            if ($id) {
                $fabricator->permissions()->detach($id);
            }
        }
    }
};
