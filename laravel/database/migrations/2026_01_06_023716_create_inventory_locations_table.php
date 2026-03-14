<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('location'); // e.g., "Warehouse A", "Bin 23", "Aisle 4-B"
            $table->integer('quantity')->default(0);
            $table->integer('quantity_committed')->default(0);
            $table->boolean('is_primary')->default(false); // Primary/default location for this product
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('location');
            $table->index('is_primary');

            // Unique constraint: one product can only have one entry per location
            $table->unique(['product_id', 'location']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_locations');
    }
};
