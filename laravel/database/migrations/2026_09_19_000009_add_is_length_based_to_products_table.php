<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a product as sold/stocked in fixed-length sticks (e.g. a 288" frame
 * extrusion) rather than as discrete units. Paired with configurator_length
 * (the stick length in inches) — when such a product is reserved from a
 * generated cut length, the reservation quantity is rounded up to the next
 * 1/10th of a stick instead of counting "1 unit per cut".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_length_based')->default(false)->after('configurator_weight_per_inch');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_length_based');
        });
    }
};
