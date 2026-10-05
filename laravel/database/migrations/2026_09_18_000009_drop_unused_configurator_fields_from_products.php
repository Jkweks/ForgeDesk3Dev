<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops configurator_available/configurator_type/configurator_use_path —
 * dormant fields carried over from ForgeDesk2, never set by any controller,
 * seeder, or import, and only ever rendered read-only (always "-") in the
 * product modal. Superseded by configurator_length/configurator_weight_per_inch
 * (2026_09_18_000008), which the new door/frame configurator actually uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['configurator_available', 'configurator_type', 'configurator_use_path']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('configurator_available')->default(false);
            $table->string('configurator_type')->nullable();
            $table->string('configurator_use_path')->nullable();
        });
    }
};
