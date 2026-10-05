<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Dimensions were stored as decimal(x, 2), which rounds fractional inches
 * (93.625" -> 93.63", 92.8125" -> 92.81") and shifts cut lengths. Widen to 4
 * places — the same precision bottom_gap and the generators' round(..., 4)
 * already use. Widening a decimal is lossless for existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_opening_specs', function ($table) {
            $table->decimal('door_opening_width', 10, 4)->change();
            $table->decimal('door_opening_height', 10, 4)->change();
        });

        Schema::table('door_frame_frame_configs', function ($table) {
            $table->decimal('total_frame_height', 10, 4)->nullable()->change();
        });

        foreach (['door_frame_frame_parts', 'door_frame_door_parts'] as $name) {
            Schema::table($name, function ($table) {
                $table->decimal('calculated_length', 10, 4)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('door_frame_opening_specs', function ($table) {
            $table->decimal('door_opening_width', 10, 2)->change();
            $table->decimal('door_opening_height', 10, 2)->change();
        });

        Schema::table('door_frame_frame_configs', function ($table) {
            $table->decimal('total_frame_height', 10, 2)->nullable()->change();
        });

        foreach (['door_frame_frame_parts', 'door_frame_door_parts'] as $name) {
            Schema::table($name, function ($table) {
                $table->decimal('calculated_length', 10, 2)->nullable()->change();
            });
        }
    }
};
