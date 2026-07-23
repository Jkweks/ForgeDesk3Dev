<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_stage_template_deps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('fd_stage_templates')->cascadeOnDelete();
            $table->foreignId('depends_on_template_id')->constrained('fd_stage_templates')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['template_id', 'depends_on_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_stage_template_deps');
    }
};
