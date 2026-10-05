<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop-rack rules for length-based stock. Together with the existing
 * minimum_drop_length (the "Min Drop"), these drive how Cut Flow treats the
 * offcut left at the end of a stick: scrap it, rack it, or split it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('drop_rack_enabled')->default(false)->after('minimum_drop_length');
            $table->decimal('drop_min_split', 10, 4)->nullable()->after('drop_rack_enabled');
            $table->decimal('drop_max_length', 10, 4)->nullable()->after('drop_min_split');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['drop_rack_enabled', 'drop_min_split', 'drop_max_length']);
        });
    }
};
