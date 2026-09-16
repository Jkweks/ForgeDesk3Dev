<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual monthly joint-count overrides for the incident-rate-by-month chart,
 * covering months before per-elevation joint_qty tracking existed. A month
 * present here always wins over whatever FdWoElevation data exists for it
 * (that data is incomplete/unreliable pre-changeover) — see
 * QualityAnalyticsController::buildIncidentRateData().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_joint_history', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->unique(); // 'YYYY-MM'
            $table->unsignedInteger('joint_count');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_joint_history');
    }
};
