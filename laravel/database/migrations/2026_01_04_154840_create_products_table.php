<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('description');
            $table->text('long_description')->nullable();
            $table->string('category')->nullable();
            $table->string('location')->nullable();
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_committed')->default(0);
            $table->integer('minimum_quantity')->default(0);
            $table->integer('maximum_quantity')->nullable();
            $table->string('unit_of_measure')->default('EA');
            $table->string('supplier')->nullable();
            $table->string('supplier_sku')->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['in_stock', 'low_stock', 'critical', 'out_of_stock'])->default('in_stock');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('sku');
            $table->index('status');
            $table->index('location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};