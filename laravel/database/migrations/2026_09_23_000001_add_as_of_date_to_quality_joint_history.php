<?php

use App\Models\QualityJointHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The '2026-09' row seeded by the 2026-09-16 changeover import is a
 * *partial*-month baseline (9/1 through 9/16 only, per its seed comment) —
 * but QualityAnalyticsController::buildIncidentRateData() was treating every
 * override row identically ("present -> use exclusively, ignore live
 * FdWoElevation data"), which is correct for the fully-historical Jan-Aug
 * rows but silently dropped every elevation completed after 9/16 from the
 * September joint count. as_of_date marks a row as a partial baseline: the
 * controller now adds live joint_qty completed on/after this date on top of
 * the override instead of replacing it outright. Left null (default) for
 * the pre-changeover months, which stay pure overrides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_joint_history', function (Blueprint $table) {
            $table->date('as_of_date')->nullable()->after('joint_count');
        });

        QualityJointHistory::where('month', '2026-09')->update(['as_of_date' => '2026-09-16']);
    }

    public function down(): void
    {
        Schema::table('quality_joint_history', function (Blueprint $table) {
            $table->dropColumn('as_of_date');
        });
    }
};
