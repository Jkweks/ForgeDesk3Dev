<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A work order request is tied to a shop drawing set, and says which elevations of it are released and how many of
 * each. Each line keeps a snapshot of the elevation (label, sizes, system) because a re-read of a drawing set replaces
 * its elevation rows; the link is re-made by label after the read, and the snapshot survives if it cannot be.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_requests', function (Blueprint $table) {
            $table->foreignId('drawing_set_id')->nullable()->after('business_job_id')->constrained('drawing_sets')->nullOnDelete();
        });

        Schema::create('work_order_request_elevations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drawing_elevation_id')->nullable()->constrained('drawing_elevations')->nullOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('quantity');                     // how many of this elevation this release covers
            $table->unsignedSmallInteger('total_reqd')->nullable();       // how many the drawing calls for in all (THUS + OPP)
            $table->decimal('ro_w', 10, 4)->nullable();
            $table->decimal('ro_h', 10, 4)->nullable();
            $table->string('system')->nullable();
            $table->string('finish')->nullable();
            $table->string('arch_ref')->nullable();
            $table->timestamps();
            $table->index('drawing_elevation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_request_elevations');
        Schema::table('work_order_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('drawing_set_id');
        });
    }
};
