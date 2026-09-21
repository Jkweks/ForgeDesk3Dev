<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardware sets are job-scoped: a set is defined for one job and applied to
 * that job's openings (door_frame_configurations). The table has zero rows
 * at the time of this migration, so business_job_id can be NOT NULL with no
 * backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurator_hwlib_sets', function (Blueprint $table) {
            $table->foreignId('business_job_id')->after('id')
                ->constrained('business_jobs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('configurator_hwlib_sets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_job_id');
        });
    }
};
