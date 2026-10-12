<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opening degree (drives the overhead stop matrix for the door and the frame) is set on the Opening tab next to
 * hand and hinging, so it lives on the opening specs. Backfilled from each opening's existing door config.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_opening_specs', function (Blueprint $table) {
            $table->unsignedSmallInteger('opening_angle')->default(90)->after('hinging');
        });

        $angles = DB::table('door_frame_door_configs')
            ->whereNotNull('opening_angle')
            ->orderBy('id')
            ->pluck('opening_angle', 'configuration_id');
        foreach ($angles as $configId => $angle) {
            DB::table('door_frame_opening_specs')->where('configuration_id', $configId)->update(['opening_angle' => $angle]);
        }
    }

    public function down(): void
    {
        Schema::table('door_frame_opening_specs', function (Blueprint $table) {
            $table->dropColumn('opening_angle');
        });
    }
};
