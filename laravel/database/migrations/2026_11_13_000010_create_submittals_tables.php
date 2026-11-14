<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Submittal tracker (docs/plans/pm-suite-integration.md, feature #7): a register of submittals per
 * job with revisions, GC/AE responses (files via pm_files), sample lines and linked job POs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submittals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_job_id')->constrained('business_jobs')->cascadeOnDelete();
            $table->string('number', 20);                       // per-job sequence: 001, 002, ...
            $table->string('name');
            $table->string('spec_section', 50)->nullable();
            $table->string('kind', 20)->default('docs');        // docs | sample | docs_samples
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('revision_number')->default(0);
            $table->date('sent_at')->nullable();
            $table->date('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_job_id', 'number']);
            $table->index('status');
        });

        Schema::create('submittal_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submittal_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status', 30);
            $table->date('sent_at')->nullable();
            $table->date('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['submittal_id', 'revision_number']);
        });

        Schema::create('submittal_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submittal_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number')->default(0);
            $table->foreignId('pm_file_id')->nullable()->constrained('pm_files')->nullOnDelete();
            $table->string('status', 30);
            $table->date('returned_at')->nullable();
            $table->string('responded_by')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('submittal_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submittal_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('manufacturer')->nullable();
            $table->string('status', 30)->default('pending');
            $table->date('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('submittal_purchase_orders', function (Blueprint $table) {
            $table->foreignId('submittal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->primary(['submittal_id', 'purchase_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submittal_purchase_orders');
        Schema::dropIfExists('submittal_samples');
        Schema::dropIfExists('submittal_responses');
        Schema::dropIfExists('submittal_revisions');
        Schema::dropIfExists('submittals');
    }
};
