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
        Schema::create('machine_tooling', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('machine_id')->constrained('machines')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');

            // Tool location on machine
            $table->string('location_on_machine')->comment('E.g., "Spindle 1", "Tool Position T12"');

            // Installation tracking
            $table->dateTime('installed_at');
            $table->string('installed_by')->nullable();
            $table->foreignId('maintenance_record_id')->nullable()->constrained('maintenance_records')->onDelete('set null');

            // Tool life tracking
            $table->decimal('tool_life_used', 10, 2)->default(0)->comment('Current usage (cycles/hours/parts)');
            $table->decimal('tool_life_remaining', 10, 2)->nullable()->comment('Calculated remaining life');

            // Status tracking
            $table->enum('status', ['active', 'warning', 'needs_replacement', 'replaced'])->default('active');

            // Removal tracking
            $table->dateTime('removed_at')->nullable();
            $table->string('removed_by')->nullable();
            $table->foreignId('replacement_maintenance_record_id')->nullable()->constrained('maintenance_records')->onDelete('set null');

            // Additional tracking
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('machine_id');
            $table->index('product_id');
            $table->index('status');
            $table->index(['machine_id', 'status']);
            $table->index('installed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machine_tooling');
    }
};
