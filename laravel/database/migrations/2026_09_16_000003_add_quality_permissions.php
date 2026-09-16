<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Quality report access:
 *  - quality.view   — list/view reports + analytics (admin, manager, office_staff)
 *  - quality.create — upload new PDF reports (admin, manager, office_staff)
 *  - quality.edit   — edit extracted fields / reassign matched elevation (admin, manager)
 *  - quality.verify — verify or reject a pending report (admin, manager)
 *  - quality.delete — delete a report (admin only)
 *
 * admin is granted every permission implicitly on the backend, but the frontend
 * hasPermission() helper checks the literal list — so grant admin explicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'quality.view' => [
                'display_name' => 'Quality Reports: View',
                'description' => 'List, view, and chart quality reports',
            ],
            'quality.create' => [
                'display_name' => 'Quality Reports: Create',
                'description' => 'Upload new quality report PDFs',
            ],
            'quality.edit' => [
                'display_name' => 'Quality Reports: Edit',
                'description' => 'Edit extracted fields and reassign the matched elevation',
            ],
            'quality.verify' => [
                'display_name' => 'Quality Reports: Verify',
                'description' => 'Verify or reject a pending quality report',
            ],
            'quality.delete' => [
                'display_name' => 'Quality Reports: Delete',
                'description' => 'Delete a quality report',
            ],
        ];

        foreach ($permissions as $name => $attrs) {
            Permission::firstOrCreate(['name' => $name], $attrs + ['category' => 'quality']);
        }

        foreach (['admin', 'manager', 'office_staff'] as $role) {
            Role::where('name', $role)->first()?->assignPermission('quality.view');
            Role::where('name', $role)->first()?->assignPermission('quality.create');
        }
        foreach (['admin', 'manager'] as $role) {
            Role::where('name', $role)->first()?->assignPermission('quality.edit');
            Role::where('name', $role)->first()?->assignPermission('quality.verify');
        }
        Role::where('name', 'admin')->first()?->assignPermission('quality.delete');
    }

    public function down(): void
    {
        foreach (['quality.view', 'quality.create', 'quality.edit', 'quality.verify', 'quality.delete'] as $name) {
            $perm = Permission::where('name', $name)->first();
            if ($perm) {
                $perm->roles()->detach();
                $perm->delete();
            }
        }
    }
};
