<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Door catalog tables, ported from fab_utils' door-calculator.html data model
 * (door_types, rails, rail_lugs, mid_lugs, glass_specs, setting_block_kits,
 * tie_rods). Unlike the frame catalog, these stay as plain PN-string reference
 * tables (matching the source 1:1) rather than product_id FKs — there are far
 * too many optional PN slots per row (5-6 on door_types/rails alone) for a
 * per-slot FK to be practical. PNs are resolved to real Product rows only when
 * DoorBomGenerator assembles the final BOM lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_door_types', function (Blueprint $table) {
            $table->id();
            $table->string('series', 20);
            $table->string('stile_name', 50);
            $table->decimal('stile_height', 6, 4);
            $table->string('bev_pn', 20)->nullable();
            $table->string('rab_pn', 20)->nullable();
            $table->string('cp_pn', 20)->nullable();
            $table->string('ast_pn', 20)->nullable();
            $table->string('inact_pn', 20)->nullable();
            $table->timestamps();

            $table->unique(['series', 'stile_name']);
        });

        Schema::create('configurator_rails', function (Blueprint $table) {
            $table->id();
            $table->enum('rail_type', ['top', 'bot', 'mid']);
            $table->string('label', 40);
            $table->string('std_pn', 20)->nullable();
            $table->string('thermal_pn', 20)->nullable();
            $table->string('mon_pn', 20)->nullable();
            $table->decimal('value_in', 8, 5);
            $table->string('stacked_std_pn', 20)->nullable();
            $table->string('stacked_thermal_pn', 20)->nullable();
            $table->string('stacked_mon_pn', 20)->nullable();
            $table->timestamps();

            $table->unique(['rail_type', 'label']);
        });

        Schema::create('configurator_rail_lugs', function (Blueprint $table) {
            $table->id();
            $table->string('rail_pn', 20)->unique();
            $table->string('lug_pn', 30);
            $table->timestamps();
        });

        Schema::create('configurator_mid_lugs', function (Blueprint $table) {
            $table->id();
            $table->string('rail_pn', 20)->unique();
            $table->string('lug_pn', 30);
            $table->string('f1_pn', 20)->nullable();
            $table->decimal('f1_qty', 5, 2)->default(0);
            $table->string('f2_pn', 20)->nullable();
            $table->decimal('f2_qty', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('configurator_glass_specs', function (Blueprint $table) {
            $table->id();
            $table->string('thickness', 10)->unique();
            $table->string('stop_pn', 20)->nullable();
            $table->string('gasket_pn', 20)->nullable();
            $table->string('gasket2_pn', 20)->nullable();
            $table->decimal('gasket_qty_factor', 4, 2)->nullable();
            $table->decimal('stop_height', 4, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('configurator_setting_block_kits', function (Blueprint $table) {
            $table->id();
            $table->string('series', 20);
            $table->string('glass_thickness', 10);
            $table->string('kit1_pn', 20)->nullable();
            $table->string('kit2_pn', 20)->nullable();
            $table->timestamps();

            $table->unique(['series', 'glass_thickness']);
        });

        Schema::create('configurator_tie_rods', function (Blueprint $table) {
            $table->id();
            $table->string('pn', 20);
            $table->decimal('min_len', 8, 4)->nullable();
            $table->decimal('max_len', 8, 4)->nullable();
            $table->string('series', 50)->nullable();
            $table->decimal('mid_val', 8, 4)->nullable();
            $table->timestamps();

            $table->index(['series', 'min_len', 'max_len']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurator_tie_rods');
        Schema::dropIfExists('configurator_setting_block_kits');
        Schema::dropIfExists('configurator_glass_specs');
        Schema::dropIfExists('configurator_mid_lugs');
        Schema::dropIfExists('configurator_rail_lugs');
        Schema::dropIfExists('configurator_rails');
        Schema::dropIfExists('configurator_door_types');
    }
};
