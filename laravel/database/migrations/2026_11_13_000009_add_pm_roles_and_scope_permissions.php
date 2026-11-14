<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Project Management roles (docs/plans/pm-suite-integration.md, feature #14).
 * PM Suite had admin/standard plus capability flags; ForgeDesk models them as roles + permissions:
 *   pm.jobs.view_all     see every PM's jobs (company scope). Without it a PM sees only their own jobs.
 *   pm.act_for_others    create/act on jobs on behalf of another PM.
 *   pm.superintendent    superintendent capabilities (field-facing PM features, e.g. crew schedule).
 *   pm.company_dashboard company-wide portfolio dashboard widgets.
 * New roles: project_manager (own jobs) and superintendent. office_staff / manager / admin keep the
 * company-wide view they already had.
 */
return new class extends Migration
{
    private const PERMS = [
        'pm.jobs.view_all' => ['PM: View All Jobs', 'See every project manager\'s jobs, not just your own'],
        'pm.act_for_others' => ['PM: Act For Others', 'Create and manage jobs on behalf of another project manager'],
        'pm.superintendent' => ['PM: Superintendent', 'Superintendent capabilities in Project Management'],
        'pm.company_dashboard' => ['PM: Company Dashboard', 'Company-wide Project Management portfolio widgets'],
    ];

    public function up(): void
    {
        foreach (self::PERMS as $name => [$display, $desc]) {
            Permission::firstOrCreate(['name' => $name], ['display_name' => $display, 'description' => $desc, 'category' => 'pm']);
        }

        $grant = function (string $role, array $perms) {
            $r = Role::where('name', $role)->first();
            foreach ($perms as $p) {
                if ($r && Permission::where('name', $p)->exists()) {
                    $r->assignPermission($p);
                }
            }
        };

        $grant('admin', array_keys(self::PERMS));
        $grant('manager', ['pm.jobs.view_all', 'pm.act_for_others', 'pm.company_dashboard']);
        $grant('office_staff', ['pm.jobs.view_all']);

        Role::firstOrCreate(['name' => 'project_manager'], [
            'display_name' => 'Project Manager',
            'description' => 'Project Management workspace: bids, own jobs, job purchase orders, submittals',
            'is_system' => false,
        ]);
        $grant('project_manager', [
            'nav.dashboard', 'nav.pm', 'pm.jobs.view', 'pm.jobs.manage',
            'orders.view', 'orders.create', 'orders.edit', 'orders.receive',
            'jobs.view', 'jobs.documents.view', 'inventory.view',
        ]);

        Role::firstOrCreate(['name' => 'superintendent'], [
            'display_name' => 'Superintendent',
            'description' => 'Project Management read access plus superintendent capabilities',
            'is_system' => false,
        ]);
        $grant('superintendent', ['nav.dashboard', 'nav.pm', 'pm.jobs.view', 'pm.jobs.view_all', 'pm.superintendent', 'jobs.view', 'jobs.documents.view']);
    }

    public function down(): void
    {
        foreach (['project_manager', 'superintendent'] as $r) {
            Role::where('name', $r)->first()?->delete();
        }
        foreach (array_keys(self::PERMS) as $name) {
            Permission::where('name', $name)->delete();
        }
    }
};
