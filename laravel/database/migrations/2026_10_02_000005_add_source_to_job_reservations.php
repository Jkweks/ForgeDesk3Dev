<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks reservations the configurator manages itself ('configurator'): exactly one per job,
 * summarising every configuration of that job that is reserved/released. Hand-made and EZ-Estimate
 * reservations keep source = NULL and are never touched by it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_reservations', function ($table) {
            $table->string('source', 30)->nullable()->index();
        });

        DB::statement("CREATE UNIQUE INDEX job_reservations_one_configurator_per_job ON job_reservations (business_job_id) WHERE source = 'configurator' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS job_reservations_one_configurator_per_job');
        Schema::table('job_reservations', function ($table) {
            $table->dropColumn('source');
        });
    }
};
