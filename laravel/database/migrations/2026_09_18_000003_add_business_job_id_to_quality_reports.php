<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job name/elevation tag on a quality report were always *derived* — from
 * the matched elevation's work order, or (Pre-Forge) from the free-text
 * job_text_guess/elevation_tag_guess. Neither path covers a large job that
 * IS tracked in ForgeDesk but whose elevation for this issue isn't entered
 * yet (mid-flight work not yet broken into elevations): there was no way to
 * pin the report to a real BusinessJob without also picking a real
 * elevation. This column lets the Job dropdown persist directly whenever
 * elevation_id is left blank for a non-Pre-Forge report; elevation_tag_guess
 * (already nullable/reused for Pre-Forge) covers the manually-typed
 * elevation text in that case.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_reports', function (Blueprint $table) {
            $table->foreignId('business_job_id')->nullable()->after('work_order_id')
                ->constrained('business_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quality_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_job_id');
        });
    }
};
