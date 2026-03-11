<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('equipment_type');
            $table->foreignId('machine_type_id')->nullable()->constrained('machine_types')->onDelete('set null');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->json('documents')->nullable();
            $table->text('notes')->nullable();
            $table->integer('total_downtime_minutes')->default(0);
            $table->timestamp('last_service_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('equipment_type');
            $table->index('location');
            $table->index('machine_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
