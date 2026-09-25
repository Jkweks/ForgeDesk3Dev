<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Retires `configurator.create` as a distinct permission — every route that
 * required it (POST /door-frame-configurations store + duplicate) now
 * requires `configurator.edit` instead, matching every other configuration-
 * mutating endpoint (opening/frame/door/hardware updates, hwlib-set apply,
 * etc.), which already all gate on `configurator.edit` alone. The two
 * permissions were always assigned to the same roles together in every
 * seed migration (admin/manager/office_staff via 2026_09_18_000003;
 * manager/fabricator via 2026_06_12_000001) — nothing in the seed history
 * ever gave one without the other — so this is a same-behavior cleanup,
 * not an access change, modulo any manual per-role tweak made outside a
 * migration (handled below by unioning before removing).
 */
return new class extends Migration
{
    public function up(): void
    {
        $createPerm = Permission::where('name', 'configurator.create')->first();
        $editPerm = Permission::where('name', 'configurator.edit')->first();

        if ($createPerm && $editPerm) {
            // Any role that has create but not edit picks up edit now, so
            // access never narrows for a role that drifted from the seeded
            // pairing (e.g. a manual admin-panel grant).
            foreach ($createPerm->roles as $role) {
                $role->assignPermission($editPerm);
            }
        }

        if ($createPerm) {
            $createPerm->roles()->detach();
            $createPerm->delete();
        }
    }

    public function down(): void
    {
        $createPerm = Permission::firstOrCreate(['name' => 'configurator.create'], [
            'display_name' => 'Configurator: Create',
            'description' => 'Create new door/frame configurations',
            'category' => 'configurator',
        ]);

        // Best-effort reverse: re-grant to whatever currently has edit.
        foreach (Role::whereHas('permissions', fn ($q) => $q->where('name', 'configurator.edit'))->get() as $role) {
            $role->assignPermission($createPerm);
        }
    }
};
