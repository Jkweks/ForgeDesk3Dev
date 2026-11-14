<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work order requests (feature #15): Project Management asks for a work order on an active job and
 * Fabrication validates and accepts it into the existing work order flow (FdWorkOrder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_job_id')->constrained('business_jobs')->cascadeOnDelete();
            $table->string('release_code', 50)->nullable();
            $table->date('requested_due_date')->nullable();
            $table->text('scope')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');      // pending | approved | rejected | withdrawn
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('fd_work_order_id')->nullable()->constrained('fd_work_orders')->nullOnDelete();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('work_order_request_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pm_file_id')->constrained('pm_files')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_request_files');
        Schema::dropIfExists('work_order_requests');
    }
};
