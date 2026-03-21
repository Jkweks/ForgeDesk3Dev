<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabrication_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('type', ['fabrication', 'installation', 'maintenance'])->default('fabrication');
            $table->string('hwtype')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('file_name')->nullable();        // Original filename
            $table->string('file_path')->nullable();        // Storage path (relative to disk root)
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_mime')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabrication_documents');
    }
};
