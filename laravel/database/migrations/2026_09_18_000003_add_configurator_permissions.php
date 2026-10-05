<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Door/Frame configurator access:
 *  - configurator.view           — browse configurations and catalog (admin, manager, office_staff)
 *  - configurator.create         — create new configurations (admin, manager, office_staff)
 *  - configurator.edit           — edit opening/frame/door specs and generate BOM (admin, manager, office_staff)
 *  - configurator.release        — lock a configuration and send it to production (admin, manager)
 *  - configurator.catalog.manage — maintain frame systems/series/profiles/components/fasteners (admin, manager)
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'configurator.view' => [
                'display_name' => 'Configurator: View',
                'description' => 'View door/frame configurations and catalog',
            ],
            'configurator.create' => [
                'display_name' => 'Configurator: Create',
                'description' => 'Create new door/frame configurations',
            ],
            'configurator.edit' => [
                'display_name' => 'Configurator: Edit',
                'description' => 'Edit configuration specs and generate the parts BOM',
            ],
            'configurator.release' => [
                'display_name' => 'Configurator: Release',
                'description' => 'Release a configuration to production',
            ],
            'configurator.catalog.manage' => [
                'display_name' => 'Configurator: Manage Catalog',
                'description' => 'Maintain frame systems, series, profiles, components, and fasteners',
            ],
            'nav.configurator' => [
                'display_name' => 'Navigation: Configurator',
                'description' => 'See the Configurator menu in the navigation bar',
            ],
        ];

        foreach ($permissions as $name => $attrs) {
            Permission::firstOrCreate(['name' => $name], $attrs + ['category' => 'configurator']);
        }

        foreach (['admin', 'manager', 'office_staff'] as $role) {
            Role::where('name', $role)->first()?->assignPermission('configurator.view');
            Role::where('name', $role)->first()?->assignPermission('configurator.create');
            Role::where('name', $role)->first()?->assignPermission('configurator.edit');
            Role::where('name', $role)->first()?->assignPermission('nav.configurator');
        }
        foreach (['admin', 'manager'] as $role) {
            Role::where('name', $role)->first()?->assignPermission('configurator.release');
            Role::where('name', $role)->first()?->assignPermission('configurator.catalog.manage');
        }
    }

    public function down(): void
    {
        foreach ([
            'configurator.view',
            'configurator.create',
            'configurator.edit',
            'configurator.release',
            'configurator.catalog.manage',
            'nav.configurator',
        ] as $name) {
            $perm = Permission::where('name', $name)->first();
            if ($perm) {
                $perm->roles()->detach();
                $perm->delete();
            }
        }
    }
};
