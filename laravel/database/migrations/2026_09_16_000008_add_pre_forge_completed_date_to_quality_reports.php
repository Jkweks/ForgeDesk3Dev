<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A manually-entered completion date for "Pre-Forge" reports (issues on jobs
 * that predate ForgeDesk tracking, so there's no real elevation with its own
 * date_completed to anchor on). Without this, the incident-rate trend would
 * fall back to report_date — the day the issue was *discovered*, not when
 * the underlying job/joint was actually produced — breaking the "anchor on
 * completion, not discovery" convention the rest of quality tracking relies
 * on. See QualityAnalyticsController::nonRejectedReportsWithAnchor().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_reports', function (Blueprint $table) {
            $table->date('pre_forge_completed_date')->nullable()->after('report_date');
        });
    }

    public function down(): void
    {
        Schema::table('quality_reports', function (Blueprint $table) {
            $table->dropColumn('pre_forge_completed_date');
        });
    }
};
