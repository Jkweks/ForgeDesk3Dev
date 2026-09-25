<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds "reserved" to the status check constraint — a new editable status
 * between draft and released that commits the configuration's current BOM
 * against real inventory via a JobReservation while still allowing edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            // SQLite (tests) doesn't enforce named CHECK constraints; nothing to alter.
            return;
        }

        DB::statement('ALTER TABLE door_frame_configurations DROP CONSTRAINT door_frame_configurations_status_check');
        DB::statement("ALTER TABLE door_frame_configurations ADD CONSTRAINT door_frame_configurations_status_check CHECK (status IN ('draft', 'reserved', 'released', 'in_progress', 'completed', 'on_hold', 'cancelled'))");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE door_frame_configurations DROP CONSTRAINT door_frame_configurations_status_check');
        DB::statement("ALTER TABLE door_frame_configurations ADD CONSTRAINT door_frame_configurations_status_check CHECK (status IN ('draft', 'released', 'in_progress', 'completed', 'on_hold', 'cancelled'))");
    }
};
