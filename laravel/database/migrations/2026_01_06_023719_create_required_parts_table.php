<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('required_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('required_product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('quantity', 10, 2)->default(1); // Quantity of required part needed
            $table->string('finish_policy')->nullable(); // e.g., 'match_parent', 'specific', 'any'
            $table->string('specific_finish')->nullable(); // If finish_policy is 'specific'
            $table->integer('sort_order')->default(0);
            $table->boolean('is_optional')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('parent_product_id');
            $table->index('required_product_id');

            // Prevent duplicate entries for the same parent-required pair
            $table->unique(['parent_product_id', 'required_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('required_parts');
    }
};
