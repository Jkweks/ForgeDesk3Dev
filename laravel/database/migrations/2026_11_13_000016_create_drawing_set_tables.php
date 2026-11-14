<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drawing import (feature #21): a shop-drawing set PDF is read by the worker into elevations with
 * R.O. / F.S. / D.L.O. sizes, printed quantities, system, finish and glass tags. Results are
 * suggestions for work orders: nothing is applied without a person confirming it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drawing_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_job_id')->constrained('business_jobs')->cascadeOnDelete();
            $table->foreignId('pm_file_id')->constrained('pm_files')->cascadeOnDelete();
            $table->string('status', 20)->default('queued');       // queued | running | done | failed
            $table->text('error')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('skipped_pages')->default(0);
            $table->string('set_finish')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['business_job_id', 'status']);
        });

        Schema::create('drawing_elevations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drawing_set_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page')->nullable();
            $table->string('label');
            $table->string('arch_ref')->nullable();
            $table->string('system')->nullable();
            $table->string('finish')->nullable();
            $table->string('finish_code', 20)->nullable();
            $table->unsignedInteger('thus_reqd')->nullable();
            $table->unsignedInteger('opp_reqd')->nullable();
            $table->unsignedInteger('total_reqd')->nullable();
            $table->decimal('ro_w', 10, 4)->nullable();
            $table->decimal('fs_w', 10, 4)->nullable();
            $table->decimal('ro_h', 10, 4)->nullable();
            $table->decimal('fs_h', 10, 4)->nullable();
            $table->decimal('door_w', 10, 4)->nullable();
            $table->decimal('door_h', 10, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('drawing_elevation_dims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drawing_elevation_id')->constrained()->cascadeOnDelete();
            $table->string('axis', 1);                        // w | h   (D.L.O. widths / heights in plan order)
            $table->unsignedInteger('position');
            $table->decimal('value', 10, 4);
        });

        Schema::create('drawing_elevation_glass_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drawing_elevation_id')->constrained()->cascadeOnDelete();
            $table->string('tag');
        });

        Schema::create('drawing_set_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drawing_set_id')->constrained()->cascadeOnDelete();
            $table->text('message');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drawing_set_warnings');
        Schema::dropIfExists('drawing_elevation_glass_tags');
        Schema::dropIfExists('drawing_elevation_dims');
        Schema::dropIfExists('drawing_elevations');
        Schema::dropIfExists('drawing_sets');
    }
};
