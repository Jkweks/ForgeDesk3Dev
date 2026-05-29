<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('net_cost', 10, 4)->nullable()->change();
            $table->decimal('price_per_length', 10, 4)->nullable()->change();
            $table->decimal('price_per_package', 10, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('net_cost', 10, 2)->nullable()->change();
            $table->decimal('price_per_length', 10, 2)->nullable()->change();
            $table->decimal('price_per_package', 10, 2)->nullable()->change();
        });
    }
};
