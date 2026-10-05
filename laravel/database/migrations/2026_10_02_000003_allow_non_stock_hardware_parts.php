<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Non-"VOS standard" hardware is usually not stocked — it's ordered per job. Those hardware
 * parts have no inventory Product, so product_id becomes optional and the part carries its own
 * manufacturer/model for the job's special-order hardware list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_hardware_parts', function ($table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->string('manufacturer', 150)->nullable()->after('part_label');
            $table->string('model_number', 150)->nullable()->after('manufacturer');
        });
    }

    public function down(): void
    {
        Schema::table('door_frame_hardware_parts', function ($table) {
            $table->dropColumn(['manufacturer', 'model_number']);
        });
    }
};
