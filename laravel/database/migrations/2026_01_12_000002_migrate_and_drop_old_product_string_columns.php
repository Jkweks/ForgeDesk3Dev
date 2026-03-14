<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, migrate any string data to foreign keys if needed
        // This handles the case where products have category/supplier as strings

        // Migrate category strings to category_id
        $productsWithCategoryString = DB::table('products')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->whereNull('category_id')
            ->get();

        foreach ($productsWithCategoryString as $product) {
            $category = DB::table('categories')
                ->where('name', $product->category)
                ->first();

            if ($category) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['category_id' => $category->id]);
            }
        }

        // Migrate supplier strings to supplier_id
        $productsWithSupplierString = DB::table('products')
            ->whereNotNull('supplier')
            ->where('supplier', '!=', '')
            ->whereNull('supplier_id')
            ->get();

        foreach ($productsWithSupplierString as $product) {
            $supplier = DB::table('suppliers')
                ->where('name', $product->supplier)
                ->first();

            if ($supplier) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['supplier_id' => $supplier->id]);
            }
        }

        // Now drop the old string columns
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['category', 'supplier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Restore the old string columns
            $table->string('category')->nullable()->after('long_description');
            $table->string('supplier')->nullable()->after('order_multiple');
        });

        // Migrate back from foreign keys to strings
        $products = DB::table('products')
            ->whereNotNull('category_id')
            ->orWhereNotNull('supplier_id')
            ->get();

        foreach ($products as $product) {
            $updates = [];

            if ($product->category_id) {
                $category = DB::table('categories')->find($product->category_id);
                if ($category) {
                    $updates['category'] = $category->name;
                }
            }

            if ($product->supplier_id) {
                $supplier = DB::table('suppliers')->find($product->supplier_id);
                if ($supplier) {
                    $updates['supplier'] = $supplier->name;
                }
            }

            if (!empty($updates)) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update($updates);
            }
        }
    }
};
