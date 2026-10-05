<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_frame_systems', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 30)->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('configurator_frame_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frame_system_id')->constrained('configurator_frame_systems')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('code', 30);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['frame_system_id', 'code']);
        });

        Schema::create('configurator_frame_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frame_series_id')->constrained('configurator_frame_series')->onDelete('cascade');
            $table->string('role_label', 100);
            $table->foreignId('product_id')->constrained('products');
            $table->json('formula');
            $table->boolean('applies_to_single')->default(true);
            $table->boolean('applies_to_pair')->default(true);
            $table->boolean('applies_to_transom')->default(false);
            $table->boolean('applies_to_threshold')->default(false);
            $table->decimal('glass_min', 8, 3)->nullable();
            $table->decimal('glass_max', 8, 3)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('frame_series_id');
        });

        Schema::create('configurator_frame_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frame_profile_id')->constrained('configurator_frame_profiles')->onDelete('cascade');
            $table->string('label', 100);
            $table->foreignId('product_id')->constrained('products');
            $table->enum('qty_type', ['per_opening', 'per_door', 'per_length'])->default('per_opening');
            $table->decimal('qty_per', 8, 3)->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('frame_profile_id');
        });

        Schema::create('configurator_frame_fasteners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frame_component_id')->constrained('configurator_frame_components')->onDelete('cascade');
            $table->string('label', 100);
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('qty_per', 8, 3)->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('frame_component_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurator_frame_fasteners');
        Schema::dropIfExists('configurator_frame_components');
        Schema::dropIfExists('configurator_frame_profiles');
        Schema::dropIfExists('configurator_frame_series');
        Schema::dropIfExists('configurator_frame_systems');
    }
};
