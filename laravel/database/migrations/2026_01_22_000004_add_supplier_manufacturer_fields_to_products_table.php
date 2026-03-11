<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Note: supplier_sku already exists, renaming conceptually to supplier_part_number
            // Adding manufacturer fields
            $table->string('manufacturer')->nullable()->after('supplier_contact');
            $table->string('manufacturer_part_number')->nullable()->after('manufacturer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'manufacturer',
                'manufacturer_part_number',
            ]);
        });
    }
};
