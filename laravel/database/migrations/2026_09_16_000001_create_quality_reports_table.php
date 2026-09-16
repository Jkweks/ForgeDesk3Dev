<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quality-issue reports ingested from uploaded PDFs. Each report is staged as
 * pending_review with a best-guess elevation match (never left unmatched —
 * low match_confidence flags it for prioritized review instead); a
 * manager/admin edits/reassigns as needed, then verifies or rejects it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('elevation_id')->nullable()->constrained('fd_wo_elevations')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('fd_work_orders')->nullOnDelete();
            $table->string('status', 20)->default('pending_review'); // pending_review | verified | rejected

            $table->date('report_date')->nullable(); // date issue discovered
            $table->dateTime('completed_at')->nullable(); // when the source form was filled out
            $table->string('inspector_name')->nullable(); // "Completed by" on the form
            $table->string('problem_type', 60)->nullable(); // e.g. Fitment Issue, Missing Hardware, ...
            $table->boolean('replacement_needed')->nullable();
            $table->text('issue_description')->nullable();

            $table->longText('raw_extracted_text')->nullable();
            $table->json('extracted_fields')->nullable();
            $table->string('elevation_tag_guess')->nullable();

            $table->boolean('auto_matched')->default(true);
            $table->decimal('match_confidence', 5, 2)->nullable();
            $table->json('match_candidates')->nullable();
            $table->foreignId('matched_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('rejected_reason')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('elevation_id');
            $table->index('work_order_id');
            $table->index('status');
            $table->index('report_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_reports');
    }
};
