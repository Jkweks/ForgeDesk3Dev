<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Job document access:
 *  - jobs.documents.view   — list + download (admin, manager, office_staff)
 *  - jobs.documents.manage — upload + delete (admin only)
 *
 * admin is granted every permission implicitly on the backend, but the frontend
 * hasPermission() helper checks the literal list — so grant admin explicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        $view = Permission::firstOrCreate(['name' => 'jobs.documents.view'], [
            'display_name' => 'Job Documents: View',
            'description' => 'List and download files attached to a job',
            'category' => 'jobs',
        ]);
        $manage = Permission::firstOrCreate(['name' => 'jobs.documents.manage'], [
            'display_name' => 'Job Documents: Manage',
            'description' => 'Upload and delete files attached to a job',
            'category' => 'jobs',
        ]);

        foreach (['admin', 'manager', 'office_staff'] as $role) {
            Role::where('name', $role)->first()?->assignPermission('jobs.documents.view');
        }
        Role::where('name', 'admin')->first()?->assignPermission('jobs.documents.manage');
    }

    public function down(): void
    {
        foreach (['jobs.documents.view', 'jobs.documents.manage'] as $name) {
            $perm = Permission::where('name', $name)->first();
            if ($perm) {
                $perm->roles()->detach();
                $perm->delete();
            }
        }
    }
};
