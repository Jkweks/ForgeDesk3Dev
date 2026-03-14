<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('door_frame_door_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuration_id')->constrained('door_frame_configurations')->onDelete('cascade');
            $table->foreignId('door_system_product_id')->constrained('products');
            $table->enum('leaf_type', ['single', 'active', 'inactive']);
            $table->foreignId('stile_product_id')->nullable()->constrained('products');
            $table->enum('glazing', ['0.25', '0.5', '1.0']);
            $table->enum('preset', ['standard', 'ws_continuous', 'ws_butt'])->nullable();
            $table->timestamps();

            // Indexes
            $table->index('configuration_id');
            $table->index('door_system_product_id');
            $table->index('leaf_type');
        });

        Schema::create('door_frame_door_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('door_config_id')->constrained('door_frame_door_configs')->onDelete('cascade');
            $table->string('part_label', 100);
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('calculated_length', 10, 2)->nullable();
            $table->boolean('is_auto_generated')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('door_config_id');
            $table->index('product_id');
            $table->index('is_auto_generated');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('door_frame_door_parts');
        Schema::dropIfExists('door_frame_door_configs');
    }
};
