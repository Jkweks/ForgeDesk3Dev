<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert any existing draft reservations to active
        DB::table('job_reservations')->where('status', 'draft')->update(['status' => 'active']);

        // Update the CHECK constraint to remove 'draft' as a valid value
        // PostgreSQL does not support dropping enum values directly;
        // we drop and re-add the constraint with the new set of values.
        DB::statement("ALTER TABLE job_reservations DROP CONSTRAINT IF EXISTS job_reservations_status_check");
        DB::statement("ALTER TABLE job_reservations ADD CONSTRAINT job_reservations_status_check CHECK (status IN ('active', 'in_progress', 'fulfilled', 'on_hold', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE job_reservations DROP CONSTRAINT IF EXISTS job_reservations_status_check");
        DB::statement("ALTER TABLE job_reservations ADD CONSTRAINT job_reservations_status_check CHECK (status IN ('draft', 'active', 'in_progress', 'fulfilled', 'on_hold', 'cancelled'))");
    }
};
