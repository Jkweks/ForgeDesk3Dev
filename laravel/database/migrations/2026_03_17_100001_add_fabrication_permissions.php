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
                'name' => 'nav.fabrication',
                'display_name' => 'Fabrication Navigation',
                'description' => 'Access to Fabrication navigation menu',
                'category' => 'navigation',
            ],
            [
                'name' => 'fabrication.view',
                'display_name' => 'Fabrication: View Documents',
                'description' => 'View fabrication documents',
                'category' => 'fabrication',
            ],
            [
                'name' => 'fabrication.create',
                'display_name' => 'Fabrication: Create Documents',
                'description' => 'Upload and create fabrication documents',
                'category' => 'fabrication',
            ],
            [
                'name' => 'fabrication.edit',
                'display_name' => 'Fabrication: Edit Documents',
                'description' => 'Edit existing fabrication documents',
                'category' => 'fabrication',
            ],
            [
                'name' => 'fabrication.delete',
                'display_name' => 'Fabrication: Delete Documents',
                'description' => 'Delete fabrication documents',
                'category' => 'fabrication',
            ],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        // Admin — full fabrication access
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            foreach ($permissions as $perm) {
                $admin->assignPermission($perm['name']);
            }
        }

        // Manager — full fabrication access
        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            foreach ($permissions as $perm) {
                $manager->assignPermission($perm['name']);
            }
        }

        // Fabricator — full fabrication access (this is their primary role)
        $fabricator = Role::where('name', 'fabricator')->first();
        if ($fabricator) {
            foreach ($permissions as $perm) {
                $fabricator->assignPermission($perm['name']);
            }
        }

        // Viewer — view only
        $viewer = Role::where('name', 'viewer')->first();
        if ($viewer) {
            $viewer->assignPermission('nav.fabrication');
            $viewer->assignPermission('fabrication.view');
        }
    }

    public function down(): void
    {
        $names = [
            'nav.fabrication',
            'fabrication.view',
            'fabrication.create',
            'fabrication.edit',
            'fabrication.delete',
        ];

        $perms = Permission::whereIn('name', $names)->get();
        foreach ($perms as $perm) {
            $perm->roles()->detach();
            $perm->delete();
        }
    }
};
