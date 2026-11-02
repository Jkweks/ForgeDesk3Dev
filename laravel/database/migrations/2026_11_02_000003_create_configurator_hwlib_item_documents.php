<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_hwlib_item_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('configurator_hwlib_items')->cascadeOnDelete();
            $table->string('doc_type', 30)->default('other'); // template, instructions, parts_manual, spec_sheet, other
            $table->string('title');
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'doc_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurator_hwlib_item_documents');
    }
};
