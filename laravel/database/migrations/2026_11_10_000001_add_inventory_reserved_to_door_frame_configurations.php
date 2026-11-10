<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An opening only commits inventory to the job reservation once someone has explicitly clicked
 * Reserve on it. Release no longer reserves on its own (e.g. precut doors that only need hardware).
 * Existing openings that already count keep counting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->boolean('inventory_reserved')->default(false)->after('cutflow_include_door');
        });

        DB::table('door_frame_configurations')
            ->whereIn('status', ['reserved', 'cut_released', 'released', 'in_progress'])
            ->update(['inventory_reserved' => true]);
    }

    public function down(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->dropColumn('inventory_reserved');
        });
    }
};
