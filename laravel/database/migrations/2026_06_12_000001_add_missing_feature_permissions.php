<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Purchase Orders ───────────────────────────────────────────────────
        $orderPerms = [
            ['name' => 'orders.submit',  'display_name' => 'Orders: Submit for Approval', 'description' => 'Submit purchase orders for approval',          'category' => 'orders'],
            ['name' => 'orders.approve', 'display_name' => 'Orders: Approve',              'description' => 'Approve submitted purchase orders',            'category' => 'orders'],
            ['name' => 'orders.receive', 'display_name' => 'Orders: Receive Materials',    'description' => 'Mark purchase order materials as received',     'category' => 'orders'],
        ];

        // ── Cycle Counting ────────────────────────────────────────────────────
        $cyclePerms = [
            ['name' => 'cycle-count.view',     'display_name' => 'Cycle Count: View',             'description' => 'View cycle count sessions',                   'category' => 'cycle-count'],
            ['name' => 'cycle-count.create',   'display_name' => 'Cycle Count: Create',           'description' => 'Create new cycle count sessions',             'category' => 'cycle-count'],
            ['name' => 'cycle-count.record',   'display_name' => 'Cycle Count: Record Counts',    'description' => 'Record physical counts during a session',      'category' => 'cycle-count'],
            ['name' => 'cycle-count.approve',  'display_name' => 'Cycle Count: Approve Variances','description' => 'Approve variances and commit adjustments',     'category' => 'cycle-count'],
            ['name' => 'cycle-count.complete', 'display_name' => 'Cycle Count: Complete Session', 'description' => 'Mark a cycle count session as complete',       'category' => 'cycle-count'],
            ['name' => 'cycle-count.cancel',   'display_name' => 'Cycle Count: Cancel Session',   'description' => 'Cancel a cycle count session',                 'category' => 'cycle-count'],
            ['name' => 'cycle-count.export',   'display_name' => 'Cycle Count: Export/PDF',       'description' => 'Export or generate PDF for cycle count',       'category' => 'cycle-count'],
        ];

        // ── Business Jobs ─────────────────────────────────────────────────────
        $jobPerms = [
            ['name' => 'jobs.view',                 'display_name' => 'Jobs: View',                   'description' => 'View business jobs',                            'category' => 'jobs'],
            ['name' => 'jobs.create',               'display_name' => 'Jobs: Create',                 'description' => 'Create new business jobs',                      'category' => 'jobs'],
            ['name' => 'jobs.edit',                 'display_name' => 'Jobs: Edit',                   'description' => 'Edit existing business jobs',                   'category' => 'jobs'],
            ['name' => 'jobs.delete',               'display_name' => 'Jobs: Delete',                 'description' => 'Delete business jobs',                          'category' => 'jobs'],
            ['name' => 'jobs.manage-reservations',  'display_name' => 'Jobs: Manage Reservations',    'description' => 'Create and manage inventory reservations for jobs', 'category' => 'jobs'],
            ['name' => 'jobs.manage-transactions',  'display_name' => 'Jobs: Manage Transactions',    'description' => 'Record and manage job transactions',            'category' => 'jobs'],
            ['name' => 'reservations.request',      'display_name' => 'Reservations: Request (Draft)','description' => 'Submit draft reservation requests for approval', 'category' => 'jobs'],
        ];

        // ── Door/Frame Configurator ───────────────────────────────────────────
        $configPerms = [
            ['name' => 'configurator.view',    'display_name' => 'Configurator: View',    'description' => 'View door/frame configurations',              'category' => 'configurator'],
            ['name' => 'configurator.create',  'display_name' => 'Configurator: Create',  'description' => 'Create new door/frame configurations',        'category' => 'configurator'],
            ['name' => 'configurator.edit',    'display_name' => 'Configurator: Edit',    'description' => 'Edit door/frame configurations',              'category' => 'configurator'],
            ['name' => 'configurator.release', 'display_name' => 'Configurator: Release', 'description' => 'Release configurations to production',        'category' => 'configurator'],
        ];

        // ── EZ Estimate / Pricing Import ──────────────────────────────────────
        $pricingPerms = [
            ['name' => 'pricing.import',  'display_name' => 'Pricing: Import EZ Estimate', 'description' => 'Upload and process EZ Estimate files', 'category' => 'pricing'],
            ['name' => 'pricing.manage',  'display_name' => 'Pricing: Manage Imported Data','description' => 'View and manage imported pricing data', 'category' => 'pricing'],
        ];

        $allNewPerms = array_merge($orderPerms, $cyclePerms, $jobPerms, $configPerms, $pricingPerms);

        foreach ($allNewPerms as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        // ── office_staff role ─────────────────────────────────────────────────
        Role::firstOrCreate(['name' => 'office_staff'], [
            'display_name' => 'Office Staff',
            'description'  => 'Can view jobs and inventory, and submit draft reservation requests for approval',
            'is_system'    => true,
        ]);

        // ── Role assignments ──────────────────────────────────────────────────

        $admin   = Role::where('name', 'admin')->first();
        $manager = Role::where('name', 'manager')->first();
        $fab     = Role::where('name', 'fabricator')->first();
        $viewer  = Role::where('name', 'viewer')->first();
        $office  = Role::where('name', 'office_staff')->first();

        // Admin always gets every permission
        if ($admin) {
            $allPermissionIds = Permission::pluck('id');
            foreach ($allPermissionIds as $pid) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id'       => $admin->id,
                    'permission_id' => $pid,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }

        // Purchase orders — approve/receive: manager; submit: manager + fabricator
        if ($manager) {
            foreach ($orderPerms as $p) {
                $manager->assignPermission($p['name']);
            }
        }
        if ($fab) {
            $fab->assignPermission('orders.submit');
        }

        // Cycle count — full: manager; view+create+record: fabricator; view: viewer
        if ($manager) {
            foreach ($cyclePerms as $p) {
                $manager->assignPermission($p['name']);
            }
        }
        if ($fab) {
            foreach (['cycle-count.view', 'cycle-count.create', 'cycle-count.record'] as $n) {
                $fab->assignPermission($n);
            }
        }
        if ($viewer) {
            $viewer->assignPermission('cycle-count.view');
        }

        // Business jobs — full (excl delete): manager + fabricator; delete: manager; view: viewer + office_staff
        $jobFullExclDelete = ['jobs.view', 'jobs.create', 'jobs.edit', 'jobs.manage-reservations', 'jobs.manage-transactions'];
        if ($manager) {
            foreach (array_merge($jobFullExclDelete, ['jobs.delete']) as $n) {
                $manager->assignPermission($n);
            }
        }
        if ($fab) {
            foreach ($jobFullExclDelete as $n) {
                $fab->assignPermission($n);
            }
        }
        if ($viewer) {
            $viewer->assignPermission('jobs.view');
        }
        if ($office) {
            $office->assignPermission('jobs.view');
            $office->assignPermission('inventory.view');
            $office->assignPermission('reservations.request');
            // Nav permissions so office_staff can reach the relevant pages
            foreach (['nav.dashboard', 'nav.inventory', 'nav.fulfillment'] as $n) {
                $office->assignPermission($n);
            }
        }

        // Door/frame configurator — full: manager + fabricator; view: viewer
        if ($manager) {
            foreach ($configPerms as $p) {
                $manager->assignPermission($p['name']);
            }
        }
        if ($fab) {
            foreach ($configPerms as $p) {
                $fab->assignPermission($p['name']);
            }
        }
        if ($viewer) {
            $viewer->assignPermission('configurator.view');
        }

        // EZ Estimate / pricing import — admin only (already handled above)
    }

    public function down(): void
    {
        $names = [
            'orders.submit', 'orders.approve', 'orders.receive',
            'cycle-count.view', 'cycle-count.create', 'cycle-count.record',
            'cycle-count.approve', 'cycle-count.complete', 'cycle-count.cancel', 'cycle-count.export',
            'jobs.view', 'jobs.create', 'jobs.edit', 'jobs.delete',
            'jobs.manage-reservations', 'jobs.manage-transactions', 'reservations.request',
            'configurator.view', 'configurator.create', 'configurator.edit', 'configurator.release',
            'pricing.import', 'pricing.manage',
        ];

        $perms = Permission::whereIn('name', $names)->get();
        foreach ($perms as $perm) {
            $perm->roles()->detach();
            $perm->delete();
        }

        $office = Role::where('name', 'office_staff')->first();
        if ($office) {
            $office->permissions()->detach();
            $office->delete();
        }
    }
};
