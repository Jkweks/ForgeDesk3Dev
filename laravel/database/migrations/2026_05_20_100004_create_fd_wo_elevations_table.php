<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_wo_elevations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('fd_work_orders')->cascadeOnDelete();
            $table->foreignId('elevation_type_id')->nullable()->constrained('fd_elevation_types')->nullOnDelete();
            $table->string('elevation_tag');
            $table->unsignedInteger('quantity')->default(1);
            $table->date('date_requested')->nullable();
            $table->date('date_completed')->nullable();
            $table->foreignId('completed_by_id')->nullable()->constrained('fd_users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_wo_elevations');
    }
};
