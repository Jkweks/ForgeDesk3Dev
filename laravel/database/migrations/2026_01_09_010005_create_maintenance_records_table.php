<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained('machines')->onDelete('cascade');
            $table->foreignId('task_id')->nullable()->constrained('maintenance_tasks')->onDelete('set null');
            $table->foreignId('asset_id')->nullable()->constrained('assets')->onDelete('set null');
            $table->string('performed_by')->nullable();
            $table->date('performed_at')->nullable();
            $table->text('notes')->nullable();
            $table->integer('downtime_minutes')->nullable();
            $table->decimal('labor_hours', 8, 2)->nullable();
            $table->json('parts_used')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('machine_id');
            $table->index('task_id');
            $table->index('asset_id');
            $table->index('performed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
