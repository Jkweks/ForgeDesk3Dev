<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What is being shipped (Metal, Glass, Doors, ...). Admins add more at any time.
        Schema::create('shipment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // The data points for a type. `phase` splits the pre-shipment (shop) and post-delivery (site) lists.
        Schema::create('shipment_check_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_type_id')->constrained('shipment_types')->cascadeOnDelete();
            $table->string('phase', 10); // pre|post
            $table->string('label');
            $table->string('input_type', 20)->default('pass_fail'); // pass_fail|number|text|choice
            $table->string('unit', 20)->nullable();
            $table->json('options')->nullable(); // choice: list of allowed values
            $table->boolean('required')->default(true);
            $table->boolean('photo_required')->default(false);
            $table->boolean('critical')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['shipment_type_id', 'phase', 'sort_order']);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number', 20)->unique();
            $table->foreignId('business_job_id')->nullable()->constrained('business_jobs')->nullOnDelete();
            $table->string('status', 30)->default('draft'); // draft|pre_check_done|shipped|delivered|delivered_issues|cancelled
            $table->date('ship_date')->nullable();
            $table->string('destination')->nullable();
            $table->string('carrier')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        // A shipment can carry several types (e.g. metal + glass on one truck).
        Schema::create('shipment_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('shipment_type_id')->constrained('shipment_types');
            $table->string('description')->nullable();
            $table->unsignedInteger('piece_count')->nullable();
            $table->timestamps();

            $table->unique(['shipment_id', 'shipment_type_id']);
        });

        Schema::create('shipment_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('phase', 10); // pre|post
            $table->string('result', 30)->default('pass'); // pass|pass_with_issues|fail
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitted_by_external')->nullable();
            $table->timestamp('performed_at');
            $table->text('notes')->nullable();
            $table->string('source', 20)->default('office'); // office|field_app
            $table->string('submission_uuid', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['shipment_id', 'phase']);
        });

        // Label/type are snapshotted so history survives later edits to the checklist.
        Schema::create('shipment_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('shipment_inspections')->cascadeOnDelete();
            $table->foreignId('shipment_type_id')->nullable()->constrained('shipment_types')->nullOnDelete();
            $table->string('type_name')->nullable();
            $table->string('label');
            $table->string('input_type', 20)->default('pass_fail');
            $table->string('unit', 20)->nullable();
            $table->string('value')->nullable(); // pass|fail|na for pass_fail, otherwise the entered/selected value
            $table->string('result', 10)->default('pass'); // pass|fail|na
            $table->boolean('critical')->default(false);
            $table->string('comment', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_inspection_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('shipment_inspections')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('shipment_inspection_items')->nullOnDelete();
            $table->string('original_name')->nullable();
            $table->string('file_path');
            $table->unsignedInteger('file_size')->default(0);
            $table->string('file_mime', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['shipment_inspection_photos', 'shipment_inspection_items', 'shipment_inspections', 'shipment_loads', 'shipments', 'shipment_check_items', 'shipment_types'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
