<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds "cut_released" — the opening's frame/door/opening data is locked and its cut list is
 * sent to CutFlow, but hardware stays editable until the full release. Also adds per-opening
 * flags to withhold the frame or door cut list from CutFlow (e.g. doors ordered precut).
 */
return new class extends Migration
{
    private const WITH = "'draft', 'reserved', 'cut_released', 'released', 'in_progress', 'completed', 'on_hold', 'cancelled'";

    private const WITHOUT = "'draft', 'reserved', 'released', 'in_progress', 'completed', 'on_hold', 'cancelled'";

    public function up(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->boolean('cutflow_include_frame')->default(true)->after('archived_at');
            $table->boolean('cutflow_include_door')->default(true)->after('cutflow_include_frame');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE door_frame_configurations DROP CONSTRAINT door_frame_configurations_status_check');
            DB::statement('ALTER TABLE door_frame_configurations ADD CONSTRAINT door_frame_configurations_status_check CHECK (status IN ('.self::WITH.'))');
        }
    }

    public function down(): void
    {
        DB::table('door_frame_configurations')->where('status', 'cut_released')->update(['status' => 'reserved']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE door_frame_configurations DROP CONSTRAINT door_frame_configurations_status_check');
            DB::statement('ALTER TABLE door_frame_configurations ADD CONSTRAINT door_frame_configurations_status_check CHECK (status IN ('.self::WITHOUT.'))');
        }

        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->dropColumn(['cutflow_include_frame', 'cutflow_include_door']);
        });
    }
};
