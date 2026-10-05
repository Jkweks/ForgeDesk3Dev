<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional second level under a hardware library category (e.g. Cylinders ->
 * Rim/Mortise/Cores/Rings, Panics -> Rim/CVR/Mortise). An item's subcategory
 * is always within its own category_id; category lists flatten a category
 * with subcategories into one "Category - Subcategory" entry per
 * subcategory, so most of the app never needs to know a category has this
 * extra level — see ConfiguratorHwlibCategory::subcategories().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_hwlib_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('configurator_hwlib_categories')->cascadeOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'name']);
        });

        Schema::table('configurator_hwlib_items', function (Blueprint $table) {
            $table->foreignId('subcategory_id')->nullable()->after('category_id')
                ->constrained('configurator_hwlib_subcategories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('configurator_hwlib_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subcategory_id');
        });
        Schema::dropIfExists('configurator_hwlib_subcategories');
    }
};
