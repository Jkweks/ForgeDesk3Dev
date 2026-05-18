<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('nonsof')->default(false)->after('safety_stock');
        });

        // Default to true for any product with no safety stock value set
        DB::statement('UPDATE products SET nonsof = true WHERE safety_stock IS NULL');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('nonsof');
        });
    }
};
