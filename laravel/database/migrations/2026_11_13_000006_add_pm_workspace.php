<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Project Management workspace (docs/plans/pm-suite-integration.md, section 4d).
 * - nav.pm / pm.jobs.view / pm.jobs.manage permissions
 * - business_jobs statuses bidding / won / lost (PM-only until the job is activated)
 */
return new class extends Migration
{
    private const PERMS = [
        'nav.pm' => ['Project Management Navigation', 'Access to the Project Management workspace', 'navigation'],
        'pm.jobs.view' => ['PM Jobs: View', 'See bidding, won and lost jobs', 'pm'],
        'pm.jobs.manage' => ['PM Jobs: Manage', 'Create bids and move jobs through bidding, won, lost and active', 'pm'],
    ];

    public function up(): void
    {
        foreach (self::PERMS as $name => [$display, $desc, $cat]) {
            Permission::firstOrCreate(['name' => $name], ['display_name' => $display, 'description' => $desc, 'category' => $cat]);
            foreach (['admin', 'manager', 'office_staff'] as $role) {
                Role::where('name', $role)->first()?->assignPermission($name);
            }
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE business_jobs DROP CONSTRAINT IF EXISTS business_jobs_status_check');
            DB::statement("ALTER TABLE business_jobs ADD CONSTRAINT business_jobs_status_check CHECK (status IN ('active', 'on_hold', 'completed', 'cancelled', 'permanent', 'bidding', 'won', 'lost'))");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("UPDATE business_jobs SET status = 'cancelled' WHERE status IN ('bidding', 'won', 'lost')");
            DB::statement('ALTER TABLE business_jobs DROP CONSTRAINT IF EXISTS business_jobs_status_check');
            DB::statement("ALTER TABLE business_jobs ADD CONSTRAINT business_jobs_status_check CHECK (status IN ('active', 'on_hold', 'completed', 'cancelled', 'permanent'))");
        }
        foreach (array_keys(self::PERMS) as $name) {
            Permission::where('name', $name)->delete();
        }
    }
};
