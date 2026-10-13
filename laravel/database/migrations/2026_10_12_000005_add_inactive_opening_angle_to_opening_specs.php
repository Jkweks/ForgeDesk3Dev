<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pair can swing its two leaves to different degrees: opening_angle is the active leaf, this is the inactive
 * leaf. Null means the inactive leaf uses the same degree as the active one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_opening_specs', function (Blueprint $table) {
            $table->unsignedSmallInteger('opening_angle_inactive')->nullable()->after('opening_angle');
        });
    }

    public function down(): void
    {
        Schema::table('door_frame_opening_specs', function (Blueprint $table) {
            $table->dropColumn('opening_angle_inactive');
        });
    }
};
