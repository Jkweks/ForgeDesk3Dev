<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job-specific purchase orders (type = job) from PM Suite.
 * docs/plans/pm-suite-integration.md: job POs never touch stock; receipts are records only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('business_job_id')->nullable()->after('type')->constrained('business_jobs')->nullOnDelete();
            $table->string('issue_stage', 40)->nullable()->after('business_job_id');
            $table->unsignedInteger('revision_number')->default(0)->after('issue_stage');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->index('issue_stage');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            // Job PO lines are free text (glass, hardware...), not stock products.
            $table->foreignId('product_id')->nullable()->change();
            $table->text('description')->nullable()->after('product_id');
            $table->string('kind', 30)->nullable()->after('description');
            $table->unsignedBigInteger('parent_item_id')->nullable()->after('kind');
            $table->unsignedInteger('sort_order')->default(0)->after('parent_item_id');
            $table->index('parent_item_id');
        });

        Schema::create('purchase_order_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->date('received_date');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('flagged')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            // Header as it stood when the revision was cut
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('issue_stage', 40)->nullable();
            $table->date('expected_date')->nullable();
            $table->string('cost_code')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['purchase_order_id', 'revision_number']);
        });

        // Lines as they stood at that revision (relational copy, not a blob)
        Schema::create('purchase_order_revision_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_revision_id')->constrained('purchase_order_revisions')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_line_id')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->string('kind', 30)->nullable();
            $table->integer('quantity_ordered')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('cost_code')->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_revision_lines');
        Schema::dropIfExists('purchase_order_revisions');
        Schema::dropIfExists('purchase_order_receipts');
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropIndex(['parent_item_id']);
            $table->dropColumn(['description', 'kind', 'parent_item_id', 'sort_order']);
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['issue_stage']);
            $table->dropConstrainedForeignId('business_job_id');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['issue_stage', 'revision_number', 'closed_at', 'voided_at', 'void_reason']);
        });
    }
};
