<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * `jobs.edit-core` gates changes to a job's identity fields (number, name,
 * customer, project manager, dates) once the job exists — separate from the
 * lighter `jobs.edit` (status / notes). Manager + admin only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'jobs.edit-core'], [
            'display_name' => 'Jobs: Edit Core Details',
            'description'  => "Edit a job's number, name, customer, project manager and dates after creation",
            'category'     => 'jobs',
        ]);

        Role::where('name', 'manager')->first()?->assignPermission('jobs.edit-core');
        // admin is granted every permission implicitly.
    }

    public function down(): void
    {
        $perm = Permission::where('name', 'jobs.edit-core')->first();
        if ($perm) {
            $perm->roles()->detach();
            $perm->delete();
        }
    }
};
