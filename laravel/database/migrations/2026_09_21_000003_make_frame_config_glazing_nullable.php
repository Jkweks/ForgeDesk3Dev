<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * frame_config.glazing was never actually read by FrameBomGenerator (only
 * transom_glazing is) — it's redundant with the newly-consolidated
 * opening_specs.glazing. Dropping the NOT NULL so the Frame tab can stop
 * asking for it without breaking existing rows. Raw SQL because this app
 * doesn't have doctrine/dbal, which Schema::table(...)->change() needs even
 * for a plain NOT NULL drop on a Postgres check-constraint "enum" column.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE door_frame_frame_configs ALTER COLUMN glazing DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE door_frame_frame_configs SET glazing = '0.25' WHERE glazing IS NULL");
        DB::statement('ALTER TABLE door_frame_frame_configs ALTER COLUMN glazing SET NOT NULL');
    }
};
