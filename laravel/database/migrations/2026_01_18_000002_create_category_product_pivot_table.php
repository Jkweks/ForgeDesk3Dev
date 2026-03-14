<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create pivot table for many-to-many relationship
        Schema::create('category_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->boolean('is_primary')->default(false); // Mark one category as primary
            $table->timestamps();

            // Ensure a product can't be added to the same category twice
            $table->unique(['product_id', 'category_id']);

            $table->index('product_id');
            $table->index('category_id');
            $table->index('is_primary');
        });

        // Migrate existing single category relationships to pivot table
        // Products with category_id will get that as their primary category
        DB::table('products')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->chunk(100, function ($products) {
                $pivotData = [];
                foreach ($products as $product) {
                    $pivotData[] = [
                        'product_id' => $product->id,
                        'category_id' => $product->category_id,
                        'is_primary' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($pivotData)) {
                    DB::table('category_product')->insert($pivotData);
                }
            });

        // Note: We'll keep the category_id column for backward compatibility
        // SQLite doesn't support modifying columns, so we skip adding the comment
        // The column remains functional for backward compatibility
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
        // category_id column remains unchanged (backward compatibility)
    }
};
