<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical properties (stock length, weight per inch) needed for future
 * configurator calculations (e.g. shipping weight, color/finish selection
 * aids). These don't vary by finish — anodizing color doesn't change an
 * extrusion's length or weight — so they're edited once per part_number and
 * propagated to every finish variant sharing that PN (see
 * ProductController::updateConfiguratorSpecs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('configurator_length', 10, 4)->nullable()->after('configurator_use_path');
            $table->decimal('configurator_weight_per_inch', 10, 4)->nullable()->after('configurator_length');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['configurator_length', 'configurator_weight_per_inch']);
        });
    }
};
