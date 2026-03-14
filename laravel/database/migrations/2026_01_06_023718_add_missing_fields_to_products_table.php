<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Part number and finish fields
            $table->string('part_number')->nullable()->after('sku');
            $table->string('finish')->nullable()->after('part_number'); // e.g., BL, C2, DB, 0R

            // Pack and UOM management
            $table->integer('pack_size')->default(1)->after('unit_of_measure');
            $table->string('purchase_uom')->default('EA')->after('pack_size');
            $table->string('stock_uom')->default('EA')->after('purchase_uom');
            $table->integer('min_order_qty')->nullable()->after('stock_uom');
            $table->integer('order_multiple')->nullable()->after('min_order_qty');

            // Advanced stock fields
            $table->integer('reorder_point')->nullable()->after('minimum_quantity');
            $table->integer('safety_stock')->nullable()->after('reorder_point');
            $table->decimal('average_daily_use', 10, 2)->nullable()->after('safety_stock');
            $table->integer('on_order_qty')->default(0)->after('average_daily_use');

            // Supplier relationship (convert from string to foreign key)
            $table->foreignId('supplier_id')->nullable()->after('supplier')->constrained()->onDelete('set null');

            // Category relationship (convert from string to foreign key)
            $table->foreignId('category_id')->nullable()->after('category')->constrained()->onDelete('set null');

            // Configurator fields
            $table->boolean('configurator_available')->default(false)->after('is_active');
            $table->string('configurator_type')->nullable()->after('configurator_available');
            $table->string('configurator_use_path')->nullable()->after('configurator_type');
            $table->decimal('dimension_height', 10, 2)->nullable()->after('configurator_use_path'); // lz in ForgeDesk2
            $table->decimal('dimension_depth', 10, 2)->nullable()->after('dimension_height'); // ly in ForgeDesk2

            // Additional tracking
            $table->boolean('is_discontinued')->default(false)->after('is_active');
            $table->string('supplier_contact')->nullable()->after('supplier_sku');

            // Add indexes for new foreign keys and commonly queried fields
            $table->index('part_number');
            $table->index('finish');
            $table->index('supplier_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['category_id']);

            $table->dropIndex(['part_number']);
            $table->dropIndex(['finish']);
            $table->dropIndex(['supplier_id']);
            $table->dropIndex(['category_id']);

            $table->dropColumn([
                'part_number',
                'finish',
                'pack_size',
                'purchase_uom',
                'stock_uom',
                'min_order_qty',
                'order_multiple',
                'reorder_point',
                'safety_stock',
                'average_daily_use',
                'on_order_qty',
                'supplier_id',
                'category_id',
                'configurator_available',
                'configurator_type',
                'configurator_use_path',
                'dimension_height',
                'dimension_depth',
                'is_discontinued',
                'supplier_contact',
            ]);
        });
    }
};
