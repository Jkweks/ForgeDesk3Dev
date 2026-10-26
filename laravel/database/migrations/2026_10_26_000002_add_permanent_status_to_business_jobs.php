<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds "permanent" to the business_jobs status check constraint — for jobs that
 * never end (interdepartment work) and are exempt from automatic status syncing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            // SQLite (tests) doesn't enforce named CHECK constraints; nothing to alter.
            return;
        }

        DB::statement('ALTER TABLE business_jobs DROP CONSTRAINT IF EXISTS business_jobs_status_check');
        DB::statement("ALTER TABLE business_jobs ADD CONSTRAINT business_jobs_status_check CHECK (status IN ('active', 'on_hold', 'completed', 'cancelled', 'permanent'))");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("UPDATE business_jobs SET status = 'active' WHERE status = 'permanent'");
        DB::statement('ALTER TABLE business_jobs DROP CONSTRAINT IF EXISTS business_jobs_status_check');
        DB::statement("ALTER TABLE business_jobs ADD CONSTRAINT business_jobs_status_check CHECK (status IN ('active', 'on_hold', 'completed', 'cancelled'))");
    }
};
