<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recurring schedules: by calendar interval, meter interval, or whichever comes first.
        Schema::create('field_asset_maint_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_asset_id')->constrained('field_assets')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('interval_days')->nullable();
            $table->decimal('interval_meter', 12, 1)->nullable();
            $table->unsignedInteger('warn_days')->default(14);
            $table->decimal('warn_meter', 12, 1)->nullable();
            $table->date('starts_on')->nullable();           // baseline before the first completion
            $table->decimal('starts_meter', 12, 1)->nullable();
            $table->date('last_done_at')->nullable();
            $table->decimal('last_done_meter', 12, 1)->nullable();
            $table->date('next_due_at')->nullable();
            $table->decimal('next_due_meter', 12, 1)->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('default_vendor')->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['field_asset_id', 'is_active']);
            $table->index('next_due_at');
        });

        Schema::create('field_asset_work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('wo_number')->unique();
            $table->foreignId('field_asset_id')->constrained('field_assets')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('field_asset_maint_plans')->nullOnDelete();
            $table->string('type', 30)->default('repair');   // preventive|repair|inspection_defect|recall|other
            $table->string('status', 30)->default('open');  // open|scheduled|in_progress|waiting_parts|completed|cancelled
            $table->string('priority', 20)->default('medium');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reported_by_external')->nullable();
            $table->date('reported_on')->nullable();
            $table->date('scheduled_for')->nullable();
            $table->date('completed_on')->nullable();
            $table->decimal('meter_at_service', 12, 1)->nullable();
            $table->string('performed_by')->nullable();
            $table->string('vendor')->nullable();
            $table->decimal('labor_hours', 8, 2)->nullable();
            $table->decimal('labor_cost', 10, 2)->nullable();
            $table->decimal('parts_cost', 10, 2)->nullable();
            $table->decimal('outside_cost', 10, 2)->nullable();
            $table->unsignedInteger('downtime_days')->nullable();
            $table->string('invoice_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 20)->default('office'); // office|field_app
            $table->unsignedBigInteger('inspection_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['field_asset_id', 'status']);
            $table->index('status');
            $table->index('completed_on');
        });

        // Line items are informational only — no inventory deduction.
        Schema::create('field_asset_wo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('field_asset_work_orders')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_asset_wo_items');
        Schema::dropIfExists('field_asset_work_orders');
        Schema::dropIfExists('field_asset_maint_plans');
    }
};
