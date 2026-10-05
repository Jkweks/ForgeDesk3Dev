<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `approver_id` is who the PO should be routed to for approval, chosen
     * per-PO at draft/submit time. Distinct from `approved_by`, which records
     * who actually clicked Approve (set later, may be a different user with
     * orders.approve permission).
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('approver_id')
                ->nullable()
                ->after('approved_by')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approver_id');
        });
    }
};
