<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed in correct order: Users -> Products -> Storage Locations -> Inventory Locations & Reservations
        $this->call([
            AdminSeeder::class,
            ProductSeeder::class,
            StorageLocationSeeder::class,
            InventoryLocationSeeder::class,
            JobReservationSeeder::class,
        ]);
    }
}