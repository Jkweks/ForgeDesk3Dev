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
        Schema::create('door_frame_frame_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuration_id')->unique()->constrained('door_frame_configurations')->onDelete('cascade');
            $table->foreignId('frame_system_product_id')->constrained('products');
            $table->enum('glazing', ['0.25', '0.5', '1.0']);
            $table->boolean('has_transom')->default(false);
            $table->enum('transom_glazing', ['0.25', '0.5', '1.0'])->nullable();
            $table->decimal('total_frame_height', 10, 2)->nullable();
            $table->timestamps();

            // Indexes
            $table->index('configuration_id');
            $table->index('frame_system_product_id');
        });

        Schema::create('door_frame_frame_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frame_config_id')->constrained('door_frame_frame_configs')->onDelete('cascade');
            $table->string('part_label', 100);
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('calculated_length', 10, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('frame_config_id');
            $table->index('product_id');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('door_frame_frame_parts');
        Schema::dropIfExists('door_frame_frame_configs');
    }
};
