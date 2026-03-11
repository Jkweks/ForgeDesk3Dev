<?php

namespace Database\Seeders;

use App\Models\StorageLocation;
use Illuminate\Database\Seeder;

class StorageLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            // Warehouse A - Main Storage
            [
                'name' => 'Warehouse A - Receiving',
                'code' => 'WH-A-RCV',
                'type' => 'zone',
                'description' => 'Receiving and inspection area',
                'aisle' => 'A',
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Warehouse A - Bin 01',
                'code' => 'WH-A-01',
                'type' => 'bin',
                'description' => 'General storage bin',
                'aisle' => 'A',
                'bay' => '01',
                'level' => '1',
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'Warehouse A - Bin 02',
                'code' => 'WH-A-02',
                'type' => 'bin',
                'description' => 'General storage bin',
                'aisle' => 'A',
                'bay' => '02',
                'level' => '1',
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'Warehouse A - Bin 03',
                'code' => 'WH-A-03',
                'type' => 'bin',
                'description' => 'General storage bin',
                'aisle' => 'A',
                'bay' => '03',
                'level' => '1',
                'is_active' => true,
                'sort_order' => 40,
            ],

            // Warehouse B - High Volume
            [
                'name' => 'Warehouse B - Rack R1-01',
                'code' => 'WH-B-R1-01',
                'type' => 'rack',
                'description' => 'High-turnover items',
                'aisle' => 'B',
                'bay' => 'R1',
                'level' => '1',
                'position' => '01',
                'is_active' => true,
                'sort_order' => 100,
            ],
            [
                'name' => 'Warehouse B - Rack R1-02',
                'code' => 'WH-B-R1-02',
                'type' => 'rack',
                'description' => 'High-turnover items',
                'aisle' => 'B',
                'bay' => 'R1',
                'level' => '1',
                'position' => '02',
                'is_active' => true,
                'sort_order' => 110,
            ],
            [
                'name' => 'Warehouse B - Rack R2-01',
                'code' => 'WH-B-R2-01',
                'type' => 'rack',
                'description' => 'Medium-turnover items',
                'aisle' => 'B',
                'bay' => 'R2',
                'level' => '1',
                'position' => '01',
                'is_active' => true,
                'sort_order' => 120,
            ],

            // Production Floor
            [
                'name' => 'Production - WIP Area',
                'code' => 'PROD-WIP',
                'type' => 'zone',
                'description' => 'Work in progress staging area',
                'is_active' => true,
                'sort_order' => 200,
            ],
            [
                'name' => 'Production - Line 1',
                'code' => 'PROD-L1',
                'type' => 'zone',
                'description' => 'Production line 1 inventory',
                'is_active' => true,
                'sort_order' => 210,
            ],
            [
                'name' => 'Production - Line 2',
                'code' => 'PROD-L2',
                'type' => 'zone',
                'description' => 'Production line 2 inventory',
                'is_active' => true,
                'sort_order' => 220,
            ],

            // Shipping
            [
                'name' => 'Shipping - Dock 1',
                'code' => 'SHIP-D1',
                'type' => 'zone',
                'description' => 'Shipping dock 1 staging',
                'is_active' => true,
                'sort_order' => 300,
            ],
            [
                'name' => 'Shipping - Dock 2',
                'code' => 'SHIP-D2',
                'type' => 'zone',
                'description' => 'Shipping dock 2 staging',
                'is_active' => true,
                'sort_order' => 310,
            ],

            // Office/Storage
            [
                'name' => 'Office Storage',
                'code' => 'OFFICE',
                'type' => 'shelf',
                'description' => 'Office supplies and small parts',
                'is_active' => true,
                'sort_order' => 400,
            ],
            [
                'name' => 'Tool Crib',
                'code' => 'TOOLS',
                'type' => 'other',
                'description' => 'Tool and equipment storage',
                'is_active' => true,
                'sort_order' => 410,
            ],

            // Quarantine/Returns
            [
                'name' => 'Quarantine Area',
                'code' => 'QUARANTINE',
                'type' => 'zone',
                'description' => 'Damaged or quarantined items',
                'is_active' => true,
                'sort_order' => 500,
            ],
            [
                'name' => 'Returns Processing',
                'code' => 'RETURNS',
                'type' => 'zone',
                'description' => 'Customer return processing area',
                'is_active' => true,
                'sort_order' => 510,
            ],
        ];

        foreach ($locations as $location) {
            StorageLocation::create($location);
        }

        $this->command->info('Created ' . count($locations) . ' storage locations');
    }
}
