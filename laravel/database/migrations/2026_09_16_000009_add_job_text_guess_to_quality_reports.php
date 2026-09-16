<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Job reference text gets the same first-class treatment elevation_tag_guess
 * already has: previously "job" only ever lived inside the read-only
 * extracted_fields->job_text JSON blob set at upload time, with no way to
 * fix or set it afterward. That mattered most for Pre-Forge reports (no
 * tracked elevation/business job to read a real name from), where the job
 * dropdown just shows "All jobs (unfiltered)" and the PDF's extracted text
 * sat underneath as a passive, uneditable hint. Backfills from the existing
 * extracted_fields->job_text so nothing already uploaded loses its reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_reports', function (Blueprint $table) {
            $table->string('job_text_guess')->nullable()->after('elevation_tag_guess');
        });

        DB::statement("
            UPDATE quality_reports
            SET job_text_guess = extracted_fields->>'job_text'
            WHERE job_text_guess IS NULL
              AND extracted_fields->>'job_text' IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('quality_reports', function (Blueprint $table) {
            $table->dropColumn('job_text_guess');
        });
    }
};
