<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Job/cost-code reference shown on the printed PO only (not editable via
     * the create/edit forms yet — filled in manually or defaulted for
     * replenishment-generated POs).
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('job_name')->nullable()->after('ship_to_location_id');
            $table->string('cost_code')->nullable()->after('job_name');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['job_name', 'cost_code']);
        });
    }
};
