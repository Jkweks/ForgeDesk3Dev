<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_wo_drawings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('fd_work_orders')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_wo_drawings');
    }
};
