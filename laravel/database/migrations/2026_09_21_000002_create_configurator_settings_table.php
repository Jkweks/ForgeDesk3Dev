<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurator-wide gap defaults (top/bottom/hinge/lock), singleton row —
 * same pattern as company_settings. Only bottom_gap actually drives a
 * calculation today (DoorBomGenerator's stile length); the rest are stored
 * ahead of the generators that will consume them, same as this app's other
 * "wire the field now, consume it later" columns (e.g. minimum_drop_length).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('top_gap', 8, 4)->default(0.1250);
            $table->decimal('bottom_gap', 8, 4)->default(0.6875);
            $table->decimal('hinge_gap', 8, 4)->default(0.0625);
            $table->decimal('lock_gap', 8, 4)->default(0.0625);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurator_settings');
    }
};
