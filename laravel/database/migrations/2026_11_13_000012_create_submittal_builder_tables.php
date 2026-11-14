<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Submittal builder (feature #8): the documents that make up a package, and the packages built from them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submittal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submittal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pm_file_id')->constrained('pm_files')->cascadeOnDelete();
            $table->foreignId('pm_product_document_id')->nullable()->constrained('pm_product_documents')->nullOnDelete();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('submittal_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submittal_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->foreignId('pm_file_id')->constrained('pm_files')->cascadeOnDelete();
            $table->unsignedInteger('item_count');
            $table->unsignedInteger('page_count')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submittal_packages');
        Schema::dropIfExists('submittal_items');
    }
};
