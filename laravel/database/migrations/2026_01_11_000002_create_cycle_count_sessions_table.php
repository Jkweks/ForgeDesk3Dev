<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cycle_count_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_number')->unique();
            $table->string('location')->nullable(); // Specific location being counted
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null'); // Count by category
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->date('scheduled_date');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null'); // Counter
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null'); // Approver
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('session_number');
            $table->index('status');
            $table->index('location');
            $table->index('scheduled_date');
        });

        Schema::create('cycle_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('cycle_count_sessions')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('location_id')->nullable()->constrained('inventory_locations')->onDelete('set null');
            $table->integer('system_quantity'); // Expected quantity from system
            $table->integer('counted_quantity')->nullable(); // Physical count
            $table->integer('variance')->default(0); // counted - system
            $table->enum('variance_status', ['pending', 'within_tolerance', 'requires_review', 'approved', 'rejected'])->default('pending');
            $table->text('count_notes')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('counted_at')->nullable();
            $table->boolean('adjustment_created')->default(false);
            $table->foreignId('transaction_id')->nullable()->constrained('inventory_transactions')->onDelete('set null');
            $table->timestamps();

            $table->index('session_id');
            $table->index('product_id');
            $table->index('variance_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cycle_count_items');
        Schema::dropIfExists('cycle_count_sessions');
    }
};
