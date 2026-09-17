<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user Quality Reports dashboard settings, starting with which date
 * drives the incident-rate-by-month line: 'report_date' (when the issue was
 * discovered — the default) or 'completed_date' (the underlying elevation's
 * completion, or the manual Pre-Forge date). We're intentionally starting on
 * report_date and plan to flip the *default* to completed_date once enough
 * data has accumulated to validate that switch — this column lets each user
 * override it in the meantime without code changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('quality_report_prefs')->nullable()->after('wo_column_prefs');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('quality_report_prefs');
        });
    }
};
