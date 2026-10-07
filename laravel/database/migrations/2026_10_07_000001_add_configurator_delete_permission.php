<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * configurator.delete — delete a (draft or reserved) door/frame configuration. Granted to
 * admin only; it can be assigned to other roles from the role editor like any permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'configurator.delete'], [
            'display_name' => 'Configurator: Delete',
            'description' => 'Delete draft or reserved door/frame configurations',
            'category' => 'configurator',
        ]);

        Role::where('name', 'admin')->first()?->assignPermission('configurator.delete');
    }

    public function down(): void
    {
        Permission::where('name', 'configurator.delete')->delete();
    }
};
