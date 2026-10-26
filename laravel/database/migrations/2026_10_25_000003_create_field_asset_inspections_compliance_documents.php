<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_asset_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_asset_id')->constrained('field_assets')->cascadeOnDelete();
            $table->string('template_name')->nullable();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitted_by_external')->nullable();
            $table->timestamp('performed_at');
            $table->decimal('meter', 12, 1)->nullable();
            $table->string('result', 30)->default('pass'); // pass|pass_with_issues|fail
            $table->text('notes')->nullable();
            $table->string('source', 20)->default('office'); // office|field_app
            $table->string('submission_uuid', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['field_asset_id', 'performed_at']);
        });

        Schema::create('field_asset_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('field_asset_inspections')->cascadeOnDelete();
            $table->string('label');
            $table->boolean('critical')->default(false);
            $table->string('result', 10)->default('pass'); // pass|fail|na
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('field_asset_compliance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_asset_id')->constrained('field_assets')->cascadeOnDelete();
            $table->string('kind', 40); // registration|insurance|dot_inspection|state_inspection|crane_cert|load_test|swing_stage_cert|calibration|warranty|other
            $table->string('reference_number')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('issuer')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['field_asset_id', 'kind']);
            $table->index('expires_on');
        });

        Schema::create('field_asset_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_asset_id')->constrained('field_assets')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('field_asset_work_orders')->nullOnDelete();
            $table->foreignId('compliance_id')->nullable()->constrained('field_asset_compliance')->nullOnDelete();
            $table->foreignId('inspection_id')->nullable()->constrained('field_asset_inspections')->nullOnDelete();
            $table->string('doc_type', 40)->default('other'); // title|registration|insurance|manual|warranty|invoice|receipt|inspection_report|certification|photo|other
            $table->string('label')->nullable();
            $table->string('original_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_mime')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['field_asset_id', 'doc_type']);
        });

        // Idempotency + audit trail for submissions pulled from the field app.
        Schema::create('field_sync_log', function (Blueprint $table) {
            $table->id();
            $table->string('submission_uuid', 64)->unique();
            $table->string('kind', 20);
            $table->string('asset_number')->nullable();
            $table->string('status', 20); // ingested|rejected|needs_review
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->string('target_type', 40)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamp('ingested_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_sync_log');
        Schema::dropIfExists('field_asset_documents');
        Schema::dropIfExists('field_asset_compliance');
        Schema::dropIfExists('field_asset_inspection_items');
        Schema::dropIfExists('field_asset_inspections');
    }
};
