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
            // Sheet header
            $table->string('project_name')->nullable();
            $table->string('gc')->nullable();
            $table->string('architect')->nullable();
            $table->string('due')->nullable();
            // Summary block
            $table->decimal('contract_amount', 14, 2)->nullable();
            $table->decimal('dollars_per_sf_glass', 14, 2)->nullable();
            $table->decimal('material', 14, 2)->nullable();
            $table->decimal('tax', 14, 2)->nullable();
            $table->decimal('large_sub', 14, 2)->nullable();
            $table->decimal('other', 14, 2)->nullable();
            $table->decimal('labor', 14, 2)->nullable();
            $table->decimal('material_overhead', 14, 2)->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_job_id', 'sheet']);
        });

        Schema::create('bid_estimate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_estimate_id')->constrained('bid_estimates')->cascadeOnDelete();
            $table->string('section', 20);                              // material | large_sub | other | labor
            $table->unsignedInteger('row');                              // sheet row
            $table->string('who')->nullable();                           // supplier / estimate tag
            $table->unsignedInteger('cost_code')->nullable();            // null = parser could not code it
            $table->unsignedInteger('additional_cost_code')->nullable();
            $table->text('description')->nullable();
            $table->decimal('qty', 14, 4)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->decimal('total', 14, 2)->nullable();
            $table->string('confidence', 40)->nullable();
            $table->string('review_reason', 60)->nullable();             // set = needs a PM decision
            $table->unique(['bid_estimate_id', 'row', 'section']);
            $table->index(['bid_estimate_id', 'cost_code']);
        });

        Schema::create('bid_estimate_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_estimate_id')->constrained('bid_estimates')->cascadeOnDelete();
            $table->string('section', 20);
            $table->unsignedInteger('row');
            $table->string('description')->nullable();
            $table->decimal('rate', 14, 4)->nullable();
            $table->decimal('base', 14, 2)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
        });

        Schema::create('bid_estimate_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_estimate_id')->constrained('bid_estimates')->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('section', 20)->nullable();
            $table->decimal('computed_from_lines', 14, 2)->nullable();
            $table->decimal('stated_in_sheet', 14, 2)->nullable();
            $table->text('note')->nullable();
        });

        Schema::create('bid_estimate_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_job_id')->constrained('business_jobs')->cascadeOnDelete();
            $table->string('sheet');
            $table->string('section', 20);
            $table->unsignedInteger('row');                              // sheet row of the parsed line
            $table->string('action', 10);                                // fix | ignore
            $table->unsignedInteger('cost_code')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_job_id', 'sheet', 'section', 'row']); // survives re-imports
        });

        foreach (require database_path('data/cost_codes.php') as [$code, $category, $description, $whenToUse]) {
            DB::table('cost_codes')->insert([
                'code' => $code, 'category' => $category, 'description' => $description,
                'when_to_use' => $whenToUse ?: null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_estimate_overrides');
        Schema::dropIfExists('bid_estimate_warnings');
        Schema::dropIfExists('bid_estimate_adjustments');
        Schema::dropIfExists('bid_estimate_lines');
        Schema::dropIfExists('bid_estimates');
        Schema::dropIfExists('cost_codes');
        Schema::dropIfExists('pm_files');
    }
};
