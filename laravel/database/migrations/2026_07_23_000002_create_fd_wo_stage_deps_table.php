<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_wo_stage_deps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('fd_wo_stages')->cascadeOnDelete();
            $table->foreignId('depends_on_stage_id')->constrained('fd_wo_stages')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['stage_id', 'depends_on_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_wo_stage_deps');
    }
};
