<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-only development tracker: bugs, feature requests and notes entered from
 * the in-app popup, used as the work queue for fixes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // bug | feature | note
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('console_output')->nullable();
            $table->string('page_url', 500)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('priority', 20)->default('normal'); // low | normal | high | critical
            $table->string('status', 20)->default('open'); // open | in_progress | resolved | wont_fix
            $table->text('resolution_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_items');
    }
};
