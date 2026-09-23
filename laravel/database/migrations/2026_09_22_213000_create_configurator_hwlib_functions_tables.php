<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardware "functions" — supplier-specific operating modes/options a hwlib
 * item can be built with (e.g. a Von Duprin 98 panic can be EO/NL/DT, and
 * independently QEL/CD). One shared code/label library
 * (configurator_hwlib_functions) is tagged onto whichever items support it
 * (configurator_hwlib_item_functions, restricts what shows in the picker for
 * that item), then a subset is actually selected per hardware link on a real
 * configuration (configurator_hwlib_link_functions) and printed on the cut
 * sheet. A function's optional group_name marks it as mutually exclusive
 * with its group-mates (e.g. EO/NL/DT share a group) — enforced in the UI,
 * not the DB, since a group is just a label, not its own table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_hwlib_functions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label');
            $table->string('group_name')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('configurator_hwlib_item_functions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('configurator_hwlib_items')->cascadeOnDelete();
            $table->foreignId('function_id')->constrained('configurator_hwlib_functions')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['item_id', 'function_id']);
        });

        Schema::create('configurator_hwlib_link_functions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('configurator_hwlib_links')->cascadeOnDelete();
            $table->foreignId('function_id')->constrained('configurator_hwlib_functions')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['link_id', 'function_id']);
        });

        // Lets the cut sheet trace a generated hardware BOM row back to the
        // hwlib link it came from, so it can print that link's selected
        // functions next to the physical part — only ever set on
        // source_type = 'item' rows (see HwlibBomGenerator::generate()).
        Schema::table('door_frame_hardware_parts', function (Blueprint $table) {
            $table->foreignId('hwlib_link_id')->nullable()->after('source_type')
                ->constrained('configurator_hwlib_links')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('door_frame_hardware_parts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hwlib_link_id');
        });
        Schema::dropIfExists('configurator_hwlib_link_functions');
        Schema::dropIfExists('configurator_hwlib_item_functions');
        Schema::dropIfExists('configurator_hwlib_functions');
    }
};
