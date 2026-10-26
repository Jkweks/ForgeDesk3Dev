<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Field & fleet assets (vehicles, trailers, swing stages, gang boxes, ...).
 * Deliberately separate from the shop maintenance module (machines / maintenance_*)
 * and the legacy `assets` table — everything here is prefixed field_asset*.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_asset_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category', 40)->default('other'); // vehicle|trailer|heavy_equipment|access_equipment|storage|power_tools|other
            $table->string('meter_type', 30)->default('none'); // odometer_miles|engine_hours|none
            $table->json('default_checklist')->nullable();    // [{label, critical}]
            $table->boolean('auto_out_of_service_on_fail')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('field_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_number')->unique();
            $table->string('name');
            $table->foreignId('field_asset_type_id')->constrained('field_asset_types');
            $table->string('status', 30)->default('active'); // active|in_service_job|out_of_service|in_repair|sold|retired
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('vin')->nullable();
            $table->string('license_plate')->nullable();
            $table->string('plate_state', 8)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->string('vendor')->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->decimal('current_meter', 12, 1)->nullable();
            $table->timestamp('meter_updated_at')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('home_location_id')->nullable()->constrained('company_locations')->nullOnDelete();
            $table->string('current_location_text')->nullable();
            $table->foreignId('current_business_job_id')->nullable()->constrained('business_jobs')->nullOnDelete();
            $table->foreignId('parent_asset_id')->nullable()->constrained('field_assets')->nullOnDelete();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('field_asset_type_id');
            $table->index('assigned_user_id');
        });

        Schema::create('field_asset_meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_asset_id')->constrained('field_assets')->cascadeOnDelete();
            $table->decimal('reading', 12, 1);
            $table->timestamp('read_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recorded_by_external')->nullable();
            $table->string('source', 20)->default('manual'); // manual|work_order|inspection|field_app
            $table->boolean('is_correction')->default(false);
            $table->timestamps();

            $table->index(['field_asset_id', 'read_at']);
        });

        $now = now();
        $items = fn (array $labels) => json_encode(array_map(fn ($l) => ['label' => $l[0], 'critical' => $l[1] ?? false], $labels));
        $seed = [
            ['Truck', 'vehicle', 'odometer_miles', [['Brakes', true], ['Tires / lug nuts'], ['Lights & signals'], ['Fluids & leaks'], ['Mirrors & glass'], ['Horn / wipers'], ['Fire extinguisher / first aid']]],
            ['Van', 'vehicle', 'odometer_miles', [['Brakes', true], ['Tires / lug nuts'], ['Lights & signals'], ['Fluids & leaks'], ['Mirrors & glass'], ['Horn / wipers']]],
            ['Trailer', 'trailer', 'none', [['Hitch / coupler', true], ['Safety chains'], ['Tires / lug nuts'], ['Lights & brakes'], ['Deck / frame condition']]],
            ['Forklift / Lift', 'heavy_equipment', 'engine_hours', [['Forks / mast', true], ['Brakes', true], ['Hydraulic leaks'], ['Tires'], ['Horn / lights / alarm']]],
            ['Swing Stage', 'access_equipment', 'none', [['Suspension wire rope', true], ['Hoists & brakes', true], ['Stirrups / platform welds', true], ['Guardrails & toe boards', true], ['Fall arrest anchors', true], ['Power cords & controls'], ['Load rating plate legible']]],
            ['Gang Box', 'storage', 'none', [['Lock / latch'], ['Lid & hinges'], ['Contents inventoried'], ['Damage / corrosion']]],
            ['Generator', 'power_tools', 'engine_hours', [['Oil level'], ['Fuel level / leaks'], ['GFCI / outlets', true], ['Exhaust / guards']]],
            ['Compressor', 'power_tools', 'engine_hours', [['Oil level'], ['Tank drain'], ['Relief valve', true], ['Hoses & fittings']]],
            ['Scaffold / Rigging', 'access_equipment', 'none', [['Frames & braces', true], ['Planks / decks'], ['Base plates / casters'], ['Rigging hardware & slings', true]]],
            ['Other', 'other', 'none', [['General condition']]],
        ];
        foreach ($seed as [$name, $category, $meter, $list]) {
            DB::table('field_asset_types')->insert([
                'name' => $name, 'category' => $category, 'meter_type' => $meter,
                'default_checklist' => $items($list),
                'auto_out_of_service_on_fail' => false, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('field_asset_meter_readings');
        Schema::dropIfExists('field_assets');
        Schema::dropIfExists('field_asset_types');
    }
};
