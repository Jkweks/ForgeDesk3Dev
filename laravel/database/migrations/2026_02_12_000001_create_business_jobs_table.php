<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_number', 100)->unique();
            $table->string('job_name', 255);
            $table->string('customer_name', 255)->nullable();
            $table->string('site_address', 500)->nullable();
            $table->string('contact_name', 255)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->enum('status', ['active', 'on_hold', 'completed', 'cancelled'])
                ->default('active');
            $table->date('start_date')->nullable();
            $table->date('target_completion_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('job_number');
            $table->index('status');
            $table->index('customer_name');
            $table->index('target_completion_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_jobs');
    }
};
