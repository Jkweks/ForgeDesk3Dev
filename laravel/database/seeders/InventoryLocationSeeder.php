<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\InventoryLocation;

class InventoryLocationSeeder extends Seeder
{
    public function run(): void
    {
        // Get products by SKU
        $products = Product::whereIn('sku', [
            'SP-2500-BL',
            'AL-1820-RAW',
            'DH-3000-SS',
            'PT-500-PW',
            'FP-250-CH',
            'BR-800-BR',
        ])->get()->keyBy('sku');

        // SP-2500-BL: Steel Plate - distributed across 3 locations
        if ($products->has('SP-2500-BL')) {
            $product = $products->get('SP-2500-BL');
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Warehouse A - Main',
                'quantity' => 250,
                'notes' => 'Primary storage location',
            ]);
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Warehouse B - Overflow',
                'quantity' => 150,
                'notes' => 'Secondary storage',
            ]);
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Production Floor',
                'quantity' => 50,
                'notes' => 'Ready for immediate use',
            ]);
        }

        // AL-1820-RAW: Aluminum Sheet - 2 locations
        if ($products->has('AL-1820-RAW')) {
            $product = $products->get('AL-1820-RAW');
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Warehouse B - Bay 5',
                'quantity' => 200,
                'notes' => 'Climate controlled storage',
            ]);
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Cutting Department',
                'quantity' => 80,
                'notes' => 'Active work in progress',
            ]);
        }

        // DH-3000-SS: Door Hinge - Critical low stock spread across locations
        if ($products->has('DH-3000-SS')) {
            $product = $products->get('DH-3000-SS');
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Warehouse C - Rack 8',
                'quantity' => 90,
                'notes' => 'Main inventory',
            ]);
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Assembly Line 1',
                'quantity' => 40,
                'notes' => 'In use for current jobs',
            ]);
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Quality Control',
                'quantity' => 20,
                'notes' => 'Inspection hold',
            ]);
        }

        // PT-500-PW: Paint - Very low stock, critical
        if ($products->has('PT-500-PW')) {
            $product = $products->get('PT-500-PW');
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Paint Shop - Main Storage',
                'quantity' => 30,
                'notes' => 'Sealed containers',
            ]);
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Paint Shop - Active Bay',
                'quantity' => 15,
                'notes' => 'Currently in use - urgent reorder needed',
            ]);
        }

        // FP-250-CH: Floor Plate - High value item
        if ($products->has('FP-250-CH')) {
            $product = $products->get('FP-250-CH');
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Secure Storage - Vault A',
                'quantity' => 180,
                'notes' => 'High value inventory',
            ]);
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Fabrication Floor',
                'quantity' => 100,
                'notes' => 'Active projects',
            ]);
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Shipping Dock',
                'quantity' => 40,
                'notes' => 'Staged for delivery',
            ]);
        }

        // BR-800-BR: Bracket - Low stock distributed
        if ($products->has('BR-800-BR')) {
            $product = $products->get('BR-800-BR');
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Small Parts - Bin 15B',
                'quantity' => 50,
                'notes' => 'Main small parts storage',
            ]);
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'Assembly Station 3',
                'quantity' => 25,
                'notes' => 'Assembly line stock',
            ]);
            InventoryLocation::create([
                'product_id' => $product->id,
                'location' => 'QC Department',
                'quantity' => 10,
                'notes' => 'Quality samples',
            ]);
        }
    }
}
