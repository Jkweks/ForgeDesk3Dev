<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('net_cost', 10, 2)->nullable()->after('unit_price');
            $table->string('pricing_category')->nullable()->after('net_cost');
            $table->decimal('finish_multiplier', 10, 4)->nullable()->after('pricing_category');
            $table->decimal('category_multiplier', 10, 4)->nullable()->after('finish_multiplier');
            $table->decimal('price_per_length', 10, 2)->nullable()->after('category_multiplier');
            $table->decimal('price_per_package', 10, 2)->nullable()->after('price_per_length');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'net_cost',
                'pricing_category',
                'finish_multiplier',
                'category_multiplier',
                'price_per_length',
                'price_per_package',
            ]);
        });
    }
};
