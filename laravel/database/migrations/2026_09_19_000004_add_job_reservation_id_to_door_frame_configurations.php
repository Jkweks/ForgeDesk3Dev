<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties a released configuration to the JobReservation that actually commits
 * its generated BOM against real inventory — set automatically by
 * DoorFrameConfigurationController::release() via
 * ConfigurationReservationBridge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->foreignId('job_reservation_id')->nullable()->after('work_order_id')
                ->constrained('job_reservations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_reservation_id');
        });
    }
};
