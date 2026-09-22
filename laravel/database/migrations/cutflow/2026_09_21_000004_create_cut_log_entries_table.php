<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->create('cut_log_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->string('part_name')->nullable();
            $table->string('finish')->nullable();
            // ForgeDesk's fd_users.id — plain integer, no real FK possible
            // across databases. operator_name is a snapshot so history reads
            // fine even if that user is later renamed/deactivated.
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->string('operator_name')->nullable();
            $table->foreignId('cut_job_id')->nullable()->constrained('cut_jobs')->nullOnDelete();
            $table->string('job_name')->nullable();
            $table->string('work_order')->nullable();
            $table->string('phase')->nullable();
            $table->string('description')->nullable();
            $table->decimal('dimension_inches', 8, 3);
            $table->string('stick_length_label')->nullable();
            $table->foreignId('stick_session_id')->nullable()->constrained('stick_sessions')->nullOnDelete();
            $table->enum('type', ['planned', 'manual'])->default('planned');
            $table->boolean('is_recut')->default(false);
            $table->boolean('is_reprint')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->dropIfExists('cut_log_entries');
    }
};
