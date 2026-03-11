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
        Schema::create('door_frame_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_job_id')->constrained('business_jobs')->onDelete('restrict');
            $table->string('configuration_name', 255)->nullable();
            $table->enum('job_scope', ['door_and_frame', 'frame_only', 'door_only']);
            $table->integer('quantity')->default(1);
            $table->enum('status', ['draft', 'released', 'in_progress', 'completed', 'on_hold', 'cancelled'])
                ->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('business_job_id');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('door_frame_configuration_doors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuration_id')->constrained('door_frame_configurations')->onDelete('cascade');
            $table->string('door_tag', 50);
            $table->timestamps();

            // Indexes
            $table->unique(['configuration_id', 'door_tag'], 'unique_config_door_tag');
            $table->index('configuration_id');
        });

        Schema::create('door_frame_opening_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuration_id')->unique()->constrained('door_frame_configurations')->onDelete('cascade');
            $table->enum('opening_type', ['single', 'pair']);
            $table->enum('hand_single', ['lh_inswing', 'rh_inswing', 'lhr', 'rhr'])->nullable();
            $table->enum('hand_pair', ['rhr_active', 'lhra_active'])->nullable();
            $table->decimal('door_opening_width', 10, 2);
            $table->decimal('door_opening_height', 10, 2);
            $table->enum('hinging', ['continuous', 'butt', 'pivot_offset', 'pivot_center']);
            $table->enum('finish', ['c2', 'db', 'bl']);
            $table->timestamps();

            // Indexes
            $table->index('configuration_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('door_frame_opening_specs');
        Schema::dropIfExists('door_frame_configuration_doors');
        Schema::dropIfExists('door_frame_configurations');
    }
};
