<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a hwlib item as a quick-added stub that hasn't been reviewed/filled
 * in yet, and gives hardware set items the same per-row function selection
 * that hardware links already have (configurator_hwlib_link_functions), so
 * functions chosen while building a set aren't lost when the set is applied
 * to a configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurator_hwlib_items', function (Blueprint $table) {
            $table->boolean('needs_review')->default(false)->after('active');
        });

        Schema::create('configurator_hwlib_set_item_functions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_item_id')->constrained('configurator_hwlib_set_items')->cascadeOnDelete();
            $table->foreignId('function_id')->constrained('configurator_hwlib_functions')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['set_item_id', 'function_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurator_hwlib_set_item_functions');

        Schema::table('configurator_hwlib_items', function (Blueprint $table) {
            $table->dropColumn('needs_review');
        });
    }
};
