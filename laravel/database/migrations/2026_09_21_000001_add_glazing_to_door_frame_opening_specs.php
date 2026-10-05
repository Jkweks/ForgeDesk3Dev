<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Glazing was entered separately on both the Frame and Door tabs even
 * though it's one physical piece of glass per opening — the Frame tab's
 * copy was never even read by FrameBomGenerator (only transom_glazing is).
 * Consolidates it onto the opening as the single source of truth; the door
 * generator now reads it from here instead of the door config.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_opening_specs', function (Blueprint $table) {
            $table->string('glazing')->nullable()->after('finish');
        });
    }

    public function down(): void
    {
        Schema::table('door_frame_opening_specs', function (Blueprint $table) {
            $table->dropColumn('glazing');
        });
    }
};
