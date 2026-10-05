<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shortest length (in inches) worth cutting as a "drop" (offcut) rather
 * than scrapping — drives the drop printout in Cut Flow. Only meaningful for
 * length-based stock products; null means "not tracked."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('minimum_drop_length', 10, 4)->nullable()->after('is_length_based');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('minimum_drop_length');
        });
    }
};
