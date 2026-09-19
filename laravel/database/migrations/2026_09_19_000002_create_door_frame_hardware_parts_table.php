<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardware BOM lines for a configuration — parallel to door_frame_frame_parts
 * / door_frame_door_parts, but hangs directly off the configuration since
 * hardware links aren't nested under either the frame_config or door_config.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('door_frame_hardware_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuration_id')->constrained('door_frame_configurations')->cascadeOnDelete();
            $table->string('part_label', 150);
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->enum('source_type', ['item', 'backer', 'fastener', 'manual'])->default('manual');
            $table->boolean('is_auto_generated')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('configuration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('door_frame_hardware_parts');
    }
};
