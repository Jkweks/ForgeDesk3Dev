<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add pricing permission
        $permission = [
            'name' => 'pricing.view',
            'display_name' => 'View Pricing',
            'category' => 'inventory',
            'description' => 'Allows viewing of cost and pricing information',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('permissions')->insert($permission);

        // Assign to admin and manager roles
        $permissionId = DB::table('permissions')->where('name', 'pricing.view')->value('id');
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $managerRoleId = DB::table('roles')->where('name', 'manager')->value('id');

        if ($permissionId && $adminRoleId) {
            DB::table('role_permissions')->insert([
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($permissionId && $managerRoleId) {
            DB::table('role_permissions')->insert([
                'role_id' => $managerRoleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'pricing.view')->value('id');

        if ($permissionId) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
