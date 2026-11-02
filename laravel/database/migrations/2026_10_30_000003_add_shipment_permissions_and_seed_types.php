<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\ShipmentCheckItem;
use App\Models\ShipmentType;
use Illuminate\Database\Migrations\Migration;

/**
 * Shipment pre/post-delivery inspections:
 *  - shipments.view          — see shipments and inspections (admin, manager, office_staff)
 *  - shipments.manage        — create/edit shipments, mark shipped (admin, manager, office_staff)
 *  - shipments.inspect       — complete the pre-shipment checklist (admin, manager, office_staff)
 *  - shipments.types.manage  — add/edit shipment types and their checklist items (admin, manager)
 *  - nav.shipments           — navigation entry
 * Post-delivery inspections are done by crews in the separate field app, not with a ForgeDesk login.
 */
return new class extends Migration
{
    private const PERMS = [
        'shipments.view' => ['Shipments: View', 'View shipments and their inspections', ['admin', 'manager', 'office_staff']],
        'shipments.manage' => ['Shipments: Manage', 'Create and edit shipments, mark them shipped', ['admin', 'manager', 'office_staff']],
        'shipments.inspect' => ['Shipments: Inspect', 'Complete pre-shipment inspection checklists', ['admin', 'manager', 'office_staff']],
        'shipments.types.manage' => ['Shipments: Manage Types', 'Add and edit shipment types and checklist items', ['admin', 'manager']],
        'nav.shipments' => ['Navigation: Shipments', 'See Shipments in the navigation bar', ['admin', 'manager', 'office_staff']],
    ];

    /** Starter types so the feature is usable on day one; admins edit these freely. */
    private const SEED = [
        'Metal' => [
            'pre' => [
                ['Piece count matches packing list', 'pass_fail', true, false],
                ['Finish free of scratches / damage', 'pass_fail', true, true],
                ['Protective film / wrap intact', 'pass_fail', false, false],
                ['Bundled and strapped securely', 'pass_fail', false, false],
            ],
            'post' => [
                ['Piece count received', 'number', true, false],
                ['Finish free of scratches / damage', 'pass_fail', true, true],
                ['Bundles intact', 'pass_fail', false, false],
            ],
        ],
        'Glass' => [
            'pre' => [
                ['Lite count matches packing list', 'pass_fail', true, false],
                ['No chips, cracks or edge damage', 'pass_fail', true, true],
                ['Racked / crated and padded', 'pass_fail', true, false],
                ['Labels match elevations', 'pass_fail', false, false],
            ],
            'post' => [
                ['Lites received', 'number', true, false],
                ['No broken or chipped lites', 'pass_fail', true, true],
                ['Crates / racks undamaged', 'pass_fail', false, true],
            ],
        ],
        'Doors' => [
            'pre' => [
                ['Door count matches packing list', 'pass_fail', true, false],
                ['Size, swing and handing verified', 'pass_fail', true, false],
                ['Finish free of damage', 'pass_fail', true, true],
                ['Hardware prep correct', 'pass_fail', false, false],
            ],
            'post' => [
                ['Doors received', 'number', true, false],
                ['No damage in transit', 'pass_fail', true, true],
                ['Hardware / accessories accounted for', 'pass_fail', false, false],
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::PERMS as $name => [$display, $desc, $roles]) {
            Permission::firstOrCreate(['name' => $name], [
                'display_name' => $display, 'description' => $desc, 'category' => 'shipments',
            ]);
            foreach ($roles as $role) {
                Role::where('name', $role)->first()?->assignPermission($name);
            }
        }

        $order = 0;
        foreach (self::SEED as $name => $phases) {
            $type = ShipmentType::firstOrCreate(['name' => $name], ['sort_order' => $order += 10]);
            if ($type->items()->exists()) {
                continue;
            }
            foreach ($phases as $phase => $items) {
                foreach ($items as $i => [$label, $input, $required, $photo]) {
                    ShipmentCheckItem::create([
                        'shipment_type_id' => $type->id, 'phase' => $phase, 'label' => $label,
                        'input_type' => $input, 'required' => $required, 'photo_required' => $photo,
                        'critical' => $photo, 'sort_order' => ($i + 1) * 10,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::PERMS) as $name) {
            $perm = Permission::where('name', $name)->first();
            if ($perm) {
                $perm->roles()->detach();
                $perm->delete();
            }
        }
    }
};
