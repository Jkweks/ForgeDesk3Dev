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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)->comment('System roles cannot be deleted');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('category')->default('general');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        // Seed default roles
        DB::table('roles')->insert([
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Full system access with all permissions',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
                'description' => 'Can manage inventory, orders, and view reports',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'fabricator',
                'display_name' => 'Fabricator',
                'description' => 'Can view and update inventory, create orders',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'viewer',
                'display_name' => 'Viewer',
                'description' => 'Read-only access to inventory and orders',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Seed default permissions
        $permissions = [
            // User Management
            ['name' => 'users.view', 'display_name' => 'View Users', 'category' => 'users'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'category' => 'users'],
            ['name' => 'users.edit', 'display_name' => 'Edit Users', 'category' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'category' => 'users'],

            // Role Management
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'category' => 'roles'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'category' => 'roles'],
            ['name' => 'roles.edit', 'display_name' => 'Edit Roles', 'category' => 'roles'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'category' => 'roles'],

            // Inventory Management
            ['name' => 'inventory.view', 'display_name' => 'View Inventory', 'category' => 'inventory'],
            ['name' => 'inventory.create', 'display_name' => 'Create Products', 'category' => 'inventory'],
            ['name' => 'inventory.edit', 'display_name' => 'Edit Products', 'category' => 'inventory'],
            ['name' => 'inventory.delete', 'display_name' => 'Delete Products', 'category' => 'inventory'],
            ['name' => 'inventory.adjust', 'display_name' => 'Adjust Inventory', 'category' => 'inventory'],

            // Orders
            ['name' => 'orders.view', 'display_name' => 'View Orders', 'category' => 'orders'],
            ['name' => 'orders.create', 'display_name' => 'Create Orders', 'category' => 'orders'],
            ['name' => 'orders.edit', 'display_name' => 'Edit Orders', 'category' => 'orders'],
            ['name' => 'orders.delete', 'display_name' => 'Delete Orders', 'category' => 'orders'],

            // Reports
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'category' => 'reports'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'category' => 'reports'],

            // Maintenance
            ['name' => 'maintenance.view', 'display_name' => 'View Maintenance', 'category' => 'maintenance'],
            ['name' => 'maintenance.manage', 'display_name' => 'Manage Maintenance', 'category' => 'maintenance'],

            // System Settings
            ['name' => 'settings.view', 'display_name' => 'View Settings', 'category' => 'settings'],
            ['name' => 'settings.edit', 'display_name' => 'Edit Settings', 'category' => 'settings'],
        ];

        foreach ($permissions as $permission) {
            $permission['created_at'] = now();
            $permission['updated_at'] = now();
            DB::table('permissions')->insert($permission);
        }

        // Assign all permissions to admin role
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $allPermissionIds = DB::table('permissions')->pluck('id');

        foreach ($allPermissionIds as $permissionId) {
            DB::table('role_permissions')->insert([
                'role_id' => $adminRoleId,
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
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
