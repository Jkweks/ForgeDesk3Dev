<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * inventory_transactions.quantity/quantity_before/quantity_after were still integer
 * after 2026_09_24_000003 decimal-ized products/inventory_locations, so completing a
 * job reservation with a fractional consumed quantity still failed writing the audit
 * transaction row ("invalid input syntax for type integer").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->decimal('quantity', 10, 1)->change();
            $table->decimal('quantity_before', 10, 1)->change();
            $table->decimal('quantity_after', 10, 1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->integer('quantity')->change();
            $table->integer('quantity_before')->change();
            $table->integer('quantity_after')->change();
        });
    }
};
