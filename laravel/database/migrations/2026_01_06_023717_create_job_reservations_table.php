<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('job_number');
            $table->string('job_name')->nullable();
            $table->integer('quantity_reserved');
            $table->date('reserved_date');
            $table->date('required_date')->nullable();
            $table->date('released_date')->nullable();
            $table->enum('status', ['active', 'fulfilled', 'cancelled', 'partially_fulfilled'])->default('active');
            $table->integer('quantity_fulfilled')->default(0);
            $table->foreignId('reserved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('released_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('job_number');
            $table->index('status');
            $table->index('reserved_date');
            $table->index('required_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_reservations');
    }
};
