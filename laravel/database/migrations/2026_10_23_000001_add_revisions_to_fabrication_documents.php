<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabrication_documents', function (Blueprint $table) {
            $table->string('revision', 50)->nullable()->after('type');
        });

        // Superseded files, kept so a replaced drawing can be recovered
        Schema::create('fabrication_document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabrication_document_id')->constrained('fabrication_documents')->cascadeOnDelete();
            $table->string('revision', 50)->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_mime')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabrication_document_revisions');
        Schema::table('fabrication_documents', function (Blueprint $table) {
            $table->dropColumn('revision');
        });
    }
};
