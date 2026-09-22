<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->create('stick_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('length_label');
            $table->string('part_name');
            $table->string('finish')->nullable();
            $table->decimal('length_inches', 8, 3);
            $table->decimal('waste_inches', 8, 3)->nullable();
            $table->enum('status', ['active', 'complete'])->default('active');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('cutflow')->create('stick_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stick_session_id')->constrained('stick_sessions')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts');
            $table->decimal('dimension_inches', 8, 3);
            $table->integer('sequence');
            $table->enum('status', ['pending', 'done'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->dropIfExists('stick_items');
        Schema::connection('cutflow')->dropIfExists('stick_sessions');
    }
};
