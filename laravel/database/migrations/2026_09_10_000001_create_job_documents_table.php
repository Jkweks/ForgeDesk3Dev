<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job-level document storage — files attached to a BusinessJob rather than a
 * work order. Typed (SOF / EZ Estimate / Purchase Order / Other) with an
 * optional free-text label; many per job per type. Stored on the local
 * (private) disk; served through an authenticated download route.
 *
 * Storage-only for now — a later pass will parse these for reporting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_job_id')->constrained('business_jobs')->cascadeOnDelete();
            $table->string('doc_type', 40);            // sof | ez_estimate | purchase_order | other
            $table->string('label')->nullable();       // required for "other", optional note otherwise
            $table->string('original_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_job_id', 'doc_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_documents');
    }
};
