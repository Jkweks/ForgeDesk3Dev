<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardware library, ported from fab_utils' isolated "hwlib" system
 * (hwlib_variables/categories/items/sets/backers/fasteners + the saved-config
 * links that attach items to an opening). This is an EAV-style prep-location
 * calculator: variables describe measurements (AFF heights, backsets, gaps),
 * items are real hardware products with per-item variable value overrides,
 * and a variable's *effective* value for a given link is resolved through a
 * priority chain (see HwlibResolver) — this migration only creates the data
 * shape, not the resolution logic.
 *
 * PNs stay as plain strings (matching the door catalog's approach) rather
 * than product_id FKs — resolved against real Product rows only when
 * HwlibBomGenerator assembles the final BOM lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_hwlib_variables', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label');
            $table->string('group_name');
            $table->enum('var_type', ['number', 'text', 'boolean', 'select', 'degree_matrix']);
            $table->string('unit', 20)->nullable();
            $table->json('options')->default('[]');
            $table->boolean('is_calculated')->default(false);
            $table->text('formula')->nullable();
            $table->string('default_value')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('overrides_variable_id')->nullable()->constrained('configurator_hwlib_variables')->nullOnDelete();
            $table->enum('side', ['door', 'frame'])->nullable();
            $table->boolean('show_in_report')->default(true);
            $table->boolean('is_inspection')->default(false);
            $table->timestamps();
        });

        Schema::create('configurator_hwlib_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('configurator_hwlib_category_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('configurator_hwlib_categories')->cascadeOnDelete();
            $table->foreignId('variable_id')->constrained('configurator_hwlib_variables')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'variable_id']);
        });

        Schema::create('configurator_hwlib_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('configurator_hwlib_categories')->restrictOnDelete();
            $table->string('name');
            $table->string('manufacturer')->nullable();
            $table->string('model_number')->nullable();
            $table->string('pn')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('vos_standard')->default(false);
            $table->json('finishes')->default('[]');
            $table->decimal('min_width', 8, 4)->nullable();
            $table->decimal('max_width', 8, 4)->nullable();
            $table->decimal('min_height', 8, 4)->nullable();
            $table->decimal('max_height', 8, 4)->nullable();
            $table->boolean('field_install')->default(false);
            $table->boolean('handed')->default(false);
            $table->foreignId('default_strike_item_id')->nullable();
            $table->foreignId('default_cover_item_id')->nullable();
            $table->timestamps();

            $table->index('category_id');
        });

        Schema::table('configurator_hwlib_items', function (Blueprint $table) {
            $table->foreign('default_strike_item_id')->references('id')->on('configurator_hwlib_items')->nullOnDelete();
            $table->foreign('default_cover_item_id')->references('id')->on('configurator_hwlib_items')->nullOnDelete();
        });

        Schema::create('configurator_hwlib_item_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('configurator_hwlib_items')->cascadeOnDelete();
            $table->foreignId('variable_id')->constrained('configurator_hwlib_variables')->cascadeOnDelete();
            $table->text('value_text')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'variable_id']);
        });

        Schema::create('configurator_hwlib_fasteners', function (Blueprint $table) {
            $table->id();
            $table->string('pn')->unique();
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('configurator_hwlib_backers', function (Blueprint $table) {
            $table->id();
            $table->string('pn')->unique();
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('configurator_hwlib_backer_fasteners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backer_id')->constrained('configurator_hwlib_backers')->cascadeOnDelete();
            $table->foreignId('fastener_id')->constrained('configurator_hwlib_fasteners')->restrictOnDelete();
            $table->decimal('qty', 8, 3)->default(1);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['backer_id', 'fastener_id']);
        });

        Schema::create('configurator_hwlib_item_backers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('configurator_hwlib_items')->cascadeOnDelete();
            $table->enum('side', ['door', 'frame']);
            $table->enum('series', ['Standard', 'Thermal', 'Monumental']);
            $table->string('pn')->nullable();
            $table->string('description')->nullable();
            $table->decimal('qty', 8, 3)->default(1);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('backer_id')->nullable()->constrained('configurator_hwlib_backers')->restrictOnDelete();
            $table->timestamps();

            $table->index('item_id');
        });

        Schema::create('configurator_hwlib_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('notes')->nullable();
            $table->boolean('is_pair')->default(false);
            $table->timestamps();
        });

        Schema::create('configurator_hwlib_set_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('configurator_hwlib_sets')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('configurator_hwlib_items')->restrictOnDelete();
            $table->integer('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->enum('series', ['Standard', 'Thermal', 'Monumental'])->default('Standard');
            $table->enum('leaf', ['both', 'active', 'inactive'])->default('both');
            $table->timestamps();

            $table->unique(['set_id', 'item_id', 'leaf']);
        });

        Schema::create('configurator_hwlib_set_item_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_item_id')->constrained('configurator_hwlib_set_items')->cascadeOnDelete();
            $table->foreignId('variable_id')->constrained('configurator_hwlib_variables')->cascadeOnDelete();
            $table->text('value_text')->nullable();
            $table->timestamps();

            $table->unique(['set_item_id', 'variable_id']);
        });

        // Links a hardware item to a door/frame configuration (this ForgeDesk's
        // equivalent of fab_utils' saved_config, which is per-step; ours is
        // per-opening, covering both the frame_config and door_config already
        // attached to it).
        Schema::create('configurator_hwlib_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuration_id')->constrained('door_frame_configurations')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('configurator_hwlib_items')->restrictOnDelete();
            $table->integer('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->enum('series', ['Standard', 'Thermal', 'Monumental'])->default('Standard');
            $table->enum('leaf', ['both', 'active', 'inactive'])->default('both');
            $table->timestamps();

            $table->unique(['configuration_id', 'item_id', 'leaf']);
        });

        Schema::create('configurator_hwlib_link_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('configurator_hwlib_links')->cascadeOnDelete();
            $table->foreignId('variable_id')->constrained('configurator_hwlib_variables')->cascadeOnDelete();
            $table->text('value_text')->nullable();
            $table->timestamps();

            $table->unique(['link_id', 'variable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurator_hwlib_link_values');
        Schema::dropIfExists('configurator_hwlib_links');
        Schema::dropIfExists('configurator_hwlib_set_item_values');
        Schema::dropIfExists('configurator_hwlib_set_items');
        Schema::dropIfExists('configurator_hwlib_sets');
        Schema::dropIfExists('configurator_hwlib_item_backers');
        Schema::dropIfExists('configurator_hwlib_backer_fasteners');
        Schema::dropIfExists('configurator_hwlib_backers');
        Schema::dropIfExists('configurator_hwlib_fasteners');
        Schema::dropIfExists('configurator_hwlib_item_values');
        Schema::table('configurator_hwlib_items', function (Blueprint $table) {
            $table->dropForeign(['default_strike_item_id']);
            $table->dropForeign(['default_cover_item_id']);
        });
        Schema::dropIfExists('configurator_hwlib_items');
        Schema::dropIfExists('configurator_hwlib_category_variables');
        Schema::dropIfExists('configurator_hwlib_categories');
        Schema::dropIfExists('configurator_hwlib_variables');
    }
};
