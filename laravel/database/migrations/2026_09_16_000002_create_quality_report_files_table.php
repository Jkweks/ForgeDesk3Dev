<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source PDF (and any re-uploads/corrections) attached to a quality report.
 * Same plain-FK / uuid-filename shape as job_documents and fd_wo_drawings;
 * stored on the local (private) disk under quality_reports/{reportId}/.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_report_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_report_id')->constrained('quality_reports')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('quality_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_report_files');
    }
};
