<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_locations', function (Blueprint $table) {
            // The "Other" entry: searched per shipment, then saved under the business name so it joins the list.
            $table->boolean('saves_visits')->default(false)->after('requires_address');
        });

        $now = now();
        DB::table('shipment_locations')->insert([
            'name' => 'Other', 'kind' => 'both', 'requires_address' => true, 'saves_visits' => true,
            'sort_order' => 1000, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('shipment_locations')->where('saves_visits', true)->delete();
        Schema::table('shipment_locations', function (Blueprint $table) {
            $table->dropColumn('saves_visits');
        });
    }
};
