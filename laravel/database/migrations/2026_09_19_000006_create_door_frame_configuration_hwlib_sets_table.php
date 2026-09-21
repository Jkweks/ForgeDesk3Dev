<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks which hardware sets are "applied" to which openings
 * (door_frame_configurations). Applying a set materializes its items as
 * configurator_hwlib_links (tagged with source_set_id) — this pivot is what
 * lets editing a set find every opening that needs to be re-synced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('door_frame_configuration_hwlib_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuration_id')->constrained('door_frame_configurations')->cascadeOnDelete();
            $table->foreignId('set_id')->constrained('configurator_hwlib_sets')->cascadeOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['configuration_id', 'set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('door_frame_configuration_hwlib_sets');
    }
};
