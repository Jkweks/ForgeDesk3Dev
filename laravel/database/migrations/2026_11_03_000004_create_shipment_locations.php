<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind', 10)->default('both');          // pickup | dropoff | both
            $table->boolean('requires_address')->default(false);  // e.g. Jobsite: the address is entered per shipment
            $table->text('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('shipment_locations')->insert([
            ['name' => 'Vos - GR', 'kind' => 'both', 'requires_address' => false, 'address' => '3800 Stahl Dr SE, Grand Rapids, MI 49546', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Vos - Lansing', 'kind' => 'both', 'requires_address' => false, 'address' => '902 Terminal Rd, Lansing, MI 48906', 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Jobsite', 'kind' => 'dropoff', 'requires_address' => true, 'address' => null, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('shipments', function (Blueprint $table) {
            foreach (['pickup', 'dropoff'] as $p) {
                $table->foreignId("{$p}_location_id")->nullable()->constrained('shipment_locations')->nullOnDelete();
                $table->text("{$p}_address")->nullable();
                $table->decimal("{$p}_lat", 10, 7)->nullable();
                $table->decimal("{$p}_lng", 10, 7)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            foreach (['pickup', 'dropoff'] as $p) {
                $table->dropConstrainedForeignId("{$p}_location_id");
                $table->dropColumn(["{$p}_address", "{$p}_lat", "{$p}_lng"]);
            }
        });
        Schema::dropIfExists('shipment_locations');
    }
};
