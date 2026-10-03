<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Block layout (order / 1-12 column span / enabled / per-column toggles) of the per-opening
 * door and frame report sheets — the "PDF template" fab_utils' Admin → PDF Layout edits. Only
 * the format of the fabricators' source-of-truth sheets lives here; the data is always live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_pdf_templates', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 20)->unique(); // door | frame
            $table->json('layout');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurator_pdf_templates');
    }
};
