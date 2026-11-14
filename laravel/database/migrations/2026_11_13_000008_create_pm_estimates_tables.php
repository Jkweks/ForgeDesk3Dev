<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BidEstimate import groundwork (docs/plans/pm-suite-integration.md, sections 4a and 4c).
 *
 * - pm_files: registry of every file Project Management works with, whether it was uploaded
 *   by hand or (later) reported by the F: share watcher. Both paths register here and run the
 *   same import jobs, so adding the watcher needs no changes downstream.
 * - cost_codes: master cost code list (seeded from database/data/cost_codes.json).
 * - bid_estimates: one parsed sheet per job; bid_estimate_overrides: a PM's manual row fixes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_files', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->default('upload');           // upload | fdrive
            $table->string('doc_type', 40);                             // bid_estimate, po_pdf, ...
            $table->string('original_name');
            $table->string('storage_path')->nullable();                 // Forge storage copy (uploads)
            $table->string('fdrive_path', 1024)->nullable();            // path on the F: share (watcher)
            $table->string('sha256', 64)->index();
            $table->unsignedBigInteger('size');
            $table->timestamp('file_mtime')->nullable();
            $table->foreignId('business_job_id')->nullable()->constrained('business_jobs')->nullOnDelete();
            $table->string('forge_doc_id', 64)->nullable()->index();    // identity marker (Forge-generated files)
            $table->unsignedInteger('revision')->nullable();
            $table->string('status', 20)->default('new');               // new | imported | review | failed
            $table->text('error')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['business_job_id', 'doc_type']);
        });

        Schema::create('cost_codes', function (Blueprint $table) {
            $table->unsignedInteger('code')->primary();
            $table->string('category', 30);
            $table->string('description');
            $table->text('when_to_use')->nullable();
            $table->timestamps();
        });

        Schema::create('bid_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_job_id')->constrained('business_jobs')->cascadeOnDelete();
            $table->foreignId('pm_file_id')->nullable()->constrained('pm_files')->nullOnDelete();
            $table->string('sheet');
            $table->string('file_sha256', 64);
            $table->unsignedInteger('parser_revision');
            $table->decimal('contract_amount', 14, 2)->nullable();
            $table->unsignedInteger('line_count')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->json('data');
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_job_id', 'sheet']);
        });

        Schema::create('bid_estimate_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_job_id')->constrained('business_jobs')->cascadeOnDelete();
            $table->string('sheet');
            $table->unsignedInteger('row');                              // sheet row of the parsed line
            $table->string('action', 10);                                // fix | ignore
            $table->unsignedInteger('cost_code')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_job_id', 'sheet', 'row']);         // survives re-imports
        });

        $seed = json_decode(file_get_contents(database_path('data/cost_codes.json')), true);
        foreach ($seed['codes'] as $c) {
            DB::table('cost_codes')->insert([
                'code' => $c['code'], 'category' => $c['category'], 'description' => $c['description'],
                'when_to_use' => $c['whenToUse'] ?: null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_estimate_overrides');
        Schema::dropIfExists('bid_estimates');
        Schema::dropIfExists('cost_codes');
        Schema::dropIfExists('pm_files');
    }
};
