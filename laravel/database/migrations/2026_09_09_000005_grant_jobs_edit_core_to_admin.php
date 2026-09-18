<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Grant `jobs.edit-core` to the admin role explicitly.
 *
 * The backend already treats admin as holding every permission, but the
 * frontend `hasPermission()` helper checks the literal permission list — so
 * without this row the Jobs Dashboard locks a job's core fields for admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'jobs.edit-core'], [
            'display_name' => 'Jobs: Edit Core Details',
            'description' => "Edit a job's number, name, customer, project manager and dates after creation",
            'category' => 'jobs',
        ]);

        Role::where('name', 'admin')->first()?->assignPermission('jobs.edit-core');
    }

    public function down(): void
    {
        $perm = Permission::where('name', 'jobs.edit-core')->first();
        $admin = Role::where('name', 'admin')->first();
        if ($perm && $admin) {
            $admin->permissions()->detach($perm->id);
        }
    }
};
