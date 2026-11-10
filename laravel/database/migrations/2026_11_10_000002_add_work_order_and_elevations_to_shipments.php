<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Set on auto-created drafts and their splits; manual shipments may leave it null (they pick elevations of their jobs' work orders).
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->constrained('fd_work_orders')->nullOnDelete();
        });

        // An elevation may sit on several shipments over time (splits, partial deliveries).
        Schema::create('shipment_elevations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('elevation_id')->constrained('fd_wo_elevations')->cascadeOnDelete();
            $table->boolean('partial')->default(false);
            $table->string('partial_note', 1000)->nullable();
            $table->timestamps();

            $table->unique(['shipment_id', 'elevation_id']);
            $table->index('elevation_id');
        });

        // Category pre-selected on the auto-created shipment when an elevation of this type arrives.
        Schema::table('fd_elevation_types', function (Blueprint $table) {
            $table->foreignId('default_shipment_type_id')->nullable()->constrained('shipment_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fd_elevation_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_shipment_type_id');
        });
        Schema::dropIfExists('shipment_elevations');
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
        });
    }
};
