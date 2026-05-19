<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            [
                'name'         => 'fabrication.work-orders.view',
                'display_name' => 'Work Orders: View',
                'description'  => 'View fabrication work orders',
                'category'     => 'fabrication',
            ],
            [
                'name'         => 'fabrication.work-orders.create',
                'display_name' => 'Work Orders: Create',
                'description'  => 'Create fabrication work orders',
                'category'     => 'fabrication',
            ],
            [
                'name'         => 'fabrication.work-orders.edit',
                'display_name' => 'Work Orders: Edit',
                'description'  => 'Edit fabrication work orders',
                'category'     => 'fabrication',
            ],
            [
                'name'         => 'fabrication.work-orders.delete',
                'display_name' => 'Work Orders: Archive/Delete',
                'description'  => 'Archive or delete fabrication work orders',
                'category'     => 'fabrication',
            ],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        // Admin and manager — full access
        foreach (['admin', 'manager'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($permissions as $perm) {
                    $role->assignPermission($perm['name']);
                }
            }
        }

        // Fabricator — full access (primary role for this feature)
        $fabricator = Role::where('name', 'fabricator')->first();
        if ($fabricator) {
            foreach ($permissions as $perm) {
                $fabricator->assignPermission($perm['name']);
            }
        }

        // Viewer — view only
        $viewer = Role::where('name', 'viewer')->first();
        if ($viewer) {
            $viewer->assignPermission('fabrication.work-orders.view');
        }
    }

    public function down(): void
    {
        $names = [
            'fabrication.work-orders.view',
            'fabrication.work-orders.create',
            'fabrication.work-orders.edit',
            'fabrication.work-orders.delete',
        ];

        $perms = Permission::whereIn('name', $names)->get();
        foreach ($perms as $perm) {
            $perm->roles()->detach();
            $perm->delete();
        }
    }
};
