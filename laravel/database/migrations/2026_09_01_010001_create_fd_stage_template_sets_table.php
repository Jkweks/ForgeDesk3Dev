<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complexity tiers: an elevation type can carry several ordered stage-template
 * lists (e.g. "Standard" / "Advanced"). An elevation is created against one tier
 * and can be bumped to a deeper one later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_stage_template_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('elevation_type_id')->constrained('fd_elevation_types')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['elevation_type_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_stage_template_sets');
    }
};
