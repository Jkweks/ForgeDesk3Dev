<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A shipment collected by the customer / a third-party carrier instead of delivered by us. No inspections;
// photos are optional (stored as shipment documents). Completed state is the `picked_up` status.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->boolean('pickup_by_others')->default(false);
            $table->string('picked_up_by', 255)->nullable();
            $table->timestamp('picked_up_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['pickup_by_others', 'picked_up_by', 'picked_up_at']);
        });
    }
};
