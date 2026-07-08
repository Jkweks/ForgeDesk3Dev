<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_issues', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('console_output')->nullable();
            $table->string('page_url')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('reported_by')->nullable();
            $table->enum('status', ['open', 'in_progress', 'resolved', 'wont_fix'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_issues');
    }
};
