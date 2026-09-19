<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a configuration be tied to the work order that actually produces it.
 * A configuration can exist well before any work order does (a large job
 * pre-configured ahead of production scheduling) — this column is set once
 * a matching work order/elevation is found, either by
 * ElevationConfigurationMatcher (when a new elevation is created) or by
 * DoorFrameConfigurationController::release() actively searching for one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->after('business_job_id')
                ->constrained('fd_work_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
        });
    }
};
