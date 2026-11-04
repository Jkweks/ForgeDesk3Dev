<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * reservations.cost.view — the material price summary on job reservations (and its Excel export).
 * Deliberately not pricing.view: that is also held by roles (e.g. retail) that must not see job costing.
 */
return new class extends Migration
{
    private const NAME = 'reservations.cost.view';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::NAME], [
            'display_name' => 'Reservations: View Cost Summary',
            'description' => 'See and export the net-price cost summary of a job reservation',
            'category' => 'reservations',
        ]);
        foreach (['admin', 'manager', 'office_staff'] as $role) {
            Role::where('name', $role)->first()?->assignPermission(self::NAME);
        }
    }

    public function down(): void
    {
        $perm = Permission::where('name', self::NAME)->first();
        if ($perm) {
            $perm->roles()->detach();
            $perm->delete();
        }
    }
};
