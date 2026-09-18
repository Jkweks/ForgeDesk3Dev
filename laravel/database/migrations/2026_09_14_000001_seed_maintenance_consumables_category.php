<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed a dedicated Category for maintenance-consumable products (pneumatic
 * fittings, clamp pads, dust collector bags/filter bags, etc). Looked up by
 * `code` elsewhere (MaintenanceController::consumables()) rather than name,
 * so it survives a rename from the Categories admin UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Category::firstOrCreate(
            ['code' => 'maintenance_consumables'],
            [
                'name' => 'Maintenance Consumables',
                'description' => 'Shop consumables used for machine upkeep — fittings, pads, filter bags, and similar wear items that are stocked and used up rather than installed and tracked.',
                'is_active' => true,
            ]
        );
    }

    public function down(): void
    {
        Category::where('code', 'maintenance_consumables')->delete();
    }
};
