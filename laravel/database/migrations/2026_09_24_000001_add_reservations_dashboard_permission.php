<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Job Reservations dashboard (/fulfillment/job-reservations):
 *  - reservations.dashboard.view — view the reservations list dashboard (admin only)
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'reservations.dashboard.view' => [
                'display_name' => 'Reservations Dashboard: View',
                'description' => 'View the Job Reservations dashboard',
            ],
        ];

        foreach ($permissions as $name => $attrs) {
            Permission::firstOrCreate(['name' => $name], $attrs + ['category' => 'reservations']);
        }

        Role::where('name', 'admin')->first()?->assignPermission('reservations.dashboard.view');
    }

    public function down(): void
    {
        $perm = Permission::where('name', 'reservations.dashboard.view')->first();
        if ($perm) {
            $perm->roles()->detach();
            $perm->delete();
        }
    }
};
