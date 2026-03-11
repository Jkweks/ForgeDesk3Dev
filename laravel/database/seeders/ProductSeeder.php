<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                // Basic Info
                'part_number' => 'SP-2500',
                'finish' => 'BL',
                'sku' => 'SP-2500-BL', // Auto-generated from part_number + finish
                'description' => 'Steel Plate 1/4" - Black Finish',
                'location' => 'A-12-03',

                // Pricing
                'unit_cost' => 45.50,
                'unit_price' => 89.99,

                // Inventory - Healthy stock levels
                'quantity_on_hand' => 450,
                'quantity_committed' => 120,
                'minimum_quantity' => 100,

                // Phase 4: Pack & UOM
                'pack_size' => 10,
                'purchase_uom' => 'CASE',
                'stock_uom' => 'EA',
                'unit_of_measure' => 'EA',

                // Phase 4: Reorder Management
                'average_daily_use' => 15.0,
                'lead_time_days' => 14,
                'safety_stock' => 50,
                'reorder_point' => 260, // (15 * 14) + 50
                'maximum_quantity' => 600,
                'on_order_qty' => 0,
                'min_order_qty' => 100,
                'order_multiple' => 10,
            ],
            [
                'part_number' => 'AL-1820',
                'finish' => 'RAW',
                'sku' => 'AL-1820-RAW',
                'description' => 'Aluminum Sheet 20ga - Raw',
                'location' => 'B-05-12',
                'unit_cost' => 32.00,
                'unit_price' => 65.00,
                'quantity_on_hand' => 280,
                'quantity_committed' => 50,
                'minimum_quantity' => 75,
                'pack_size' => 25,
                'purchase_uom' => 'BUNDLE',
                'stock_uom' => 'EA',
                'unit_of_measure' => 'EA',
                'average_daily_use' => 8.5,
                'lead_time_days' => 10,
                'safety_stock' => 30,
                'reorder_point' => 115, // (8.5 * 10) + 30
                'maximum_quantity' => 400,
                'on_order_qty' => 100,
                'min_order_qty' => 50,
                'order_multiple' => 25,
            ],
            [
                // Product below reorder point - triggers alert
                'part_number' => 'DH-3000',
                'finish' => 'SS',
                'sku' => 'DH-3000-SS',
                'description' => 'Door Hinge Heavy Duty - Stainless Steel',
                'location' => 'C-08-15',
                'unit_cost' => 12.50,
                'unit_price' => 24.99,
                'quantity_on_hand' => 150, // Below reorder point!
                'quantity_committed' => 50,
                'minimum_quantity' => 200,
                'pack_size' => 100,
                'purchase_uom' => 'BOX',
                'stock_uom' => 'EA',
                'unit_of_measure' => 'EA',
                'average_daily_use' => 25.0,
                'lead_time_days' => 7,
                'safety_stock' => 100,
                'reorder_point' => 275, // (25 * 7) + 100
                'maximum_quantity' => 800,
                'on_order_qty' => 200, // Order already placed
                'min_order_qty' => 100,
                'order_multiple' => 100,
            ],
            [
                // Critical stock level - low days until stockout
                'part_number' => 'PT-500',
                'finish' => 'PW',
                'sku' => 'PT-500-PW',
                'description' => 'Paint - Industrial Grey - Powder Coat White',
                'location' => 'D-03-07',
                'unit_cost' => 28.00,
                'unit_price' => 55.99,
                'quantity_on_hand' => 45, // Very low stock - only 3 days left!
                'quantity_committed' => 10,
                'minimum_quantity' => 50,
                'pack_size' => 4,
                'purchase_uom' => 'CASE',
                'stock_uom' => 'GAL',
                'unit_of_measure' => 'GAL',
                'average_daily_use' => 12.0,
                'lead_time_days' => 21,
                'safety_stock' => 50,
                'reorder_point' => 302, // (12 * 21) + 50 - way below reorder!
                'maximum_quantity' => 400,
                'on_order_qty' => 240,
                'min_order_qty' => 48,
                'order_multiple' => 4,
            ],
            [
                'part_number' => 'WS-1000',
                'finish' => null, // No finish code
                'sku' => 'WS-1000',
                'description' => 'Weatherstripping 100ft Roll',
                'location' => 'E-11-20',
                'unit_cost' => 18.00,
                'unit_price' => 39.99,
                'quantity_on_hand' => 120,
                'quantity_committed' => 0,
                'minimum_quantity' => 50,
                'pack_size' => 1,
                'purchase_uom' => 'EA',
                'stock_uom' => 'EA',
                'unit_of_measure' => 'ROLL',
                'average_daily_use' => 3.5,
                'lead_time_days' => 14,
                'safety_stock' => 20,
                'reorder_point' => 69, // (3.5 * 14) + 20
                'maximum_quantity' => 150,
                'on_order_qty' => 0,
                'min_order_qty' => 10,
                'order_multiple' => 1,
            ],
            [
                'part_number' => 'FP-250',
                'finish' => 'CH',
                'sku' => 'FP-250-CH',
                'description' => 'Floor Plate Diamond - Chrome',
                'location' => 'A-08-22',
                'unit_cost' => 85.00,
                'unit_price' => 165.00,
                'quantity_on_hand' => 320,
                'quantity_committed' => 200,
                'minimum_quantity' => 150,
                'pack_size' => 5,
                'purchase_uom' => 'PALLET',
                'stock_uom' => 'EA',
                'unit_of_measure' => 'EA',
                'average_daily_use' => 10.0,
                'lead_time_days' => 28,
                'safety_stock' => 100,
                'reorder_point' => 380, // (10 * 28) + 100
                'maximum_quantity' => 600,
                'on_order_qty' => 0,
                'min_order_qty' => 50,
                'order_multiple' => 5,
            ],
            [
                // Another low stock item
                'part_number' => 'BR-800',
                'finish' => 'BR',
                'sku' => 'BR-800-BR',
                'description' => 'Bracket L-Shape - Bronze',
                'location' => 'B-15-10',
                'unit_cost' => 6.75,
                'unit_price' => 14.99,
                'quantity_on_hand' => 85,
                'quantity_committed' => 40,
                'minimum_quantity' => 100,
                'pack_size' => 50,
                'purchase_uom' => 'BOX',
                'stock_uom' => 'EA',
                'unit_of_measure' => 'EA',
                'average_daily_use' => 18.0,
                'lead_time_days' => 10,
                'safety_stock' => 75,
                'reorder_point' => 255, // (18 * 10) + 75 - way below!
                'maximum_quantity' => 500,
                'on_order_qty' => 200,
                'min_order_qty' => 100,
                'order_multiple' => 50,
            ],
            [
                'part_number' => 'SL-400',
                'finish' => 'AL',
                'sku' => 'SL-400-AL',
                'description' => 'Sliding Track 96" - Aluminum',
                'location' => 'C-20-05',
                'unit_cost' => 42.00,
                'unit_price' => 89.99,
                'quantity_on_hand' => 180,
                'quantity_committed' => 75,
                'minimum_quantity' => 80,
                'pack_size' => 6,
                'purchase_uom' => 'BUNDLE',
                'stock_uom' => 'EA',
                'unit_of_measure' => 'EA',
                'average_daily_use' => 5.0,
                'lead_time_days' => 21,
                'safety_stock' => 40,
                'reorder_point' => 145, // (5 * 21) + 40
                'maximum_quantity' => 300,
                'on_order_qty' => 0,
                'min_order_qty' => 30,
                'order_multiple' => 6,
            ],
        ];

        foreach ($products as $productData) {
            $product = Product::create($productData);
            $product->updateStatus();
        }
    }
}