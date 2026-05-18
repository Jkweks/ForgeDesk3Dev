<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'fabrication.work-orders.view',   'description' => 'View fabrication work orders'],
            ['name' => 'fabrication.work-orders.create', 'description' => 'Create fabrication work orders'],
            ['name' => 'fabrication.work-orders.edit',   'description' => 'Edit fabrication work orders'],
            ['name' => 'fabrication.work-orders.delete', 'description' => 'Archive/delete fabrication work orders'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name'        => $permission['name'],
                'description' => $permission['description'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', [
            'fabrication.work-orders.view',
            'fabrication.work-orders.create',
            'fabrication.work-orders.edit',
            'fabrication.work-orders.delete',
        ])->delete();
    }
};
