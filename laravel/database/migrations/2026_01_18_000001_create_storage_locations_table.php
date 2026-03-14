<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., "Warehouse A - Bin 23"
            $table->string('code')->unique()->nullable(); // Short code like "WH-A-23"
            $table->string('type')->default('bin'); // warehouse, shelf, bin, rack, zone, other
            $table->text('description')->nullable();

            // Location hierarchy/addressing
            $table->string('aisle')->nullable(); // e.g., "A1"
            $table->string('bay')->nullable(); // e.g., "05"
            $table->string('level')->nullable(); // e.g., "2"
            $table->string('position')->nullable(); // e.g., "03"

            // Optional capacity tracking
            $table->decimal('capacity', 10, 2)->nullable(); // Maximum capacity
            $table->string('capacity_unit')->nullable(); // units, cubic_ft, pallets, etc.

            // Status and management
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('code');
            $table->index('type');
            $table->index('is_active');
            $table->index('aisle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_locations');
    }
};
