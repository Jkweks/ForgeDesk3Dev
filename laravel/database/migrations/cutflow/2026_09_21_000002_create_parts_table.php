<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'cutflow';

    /**
     * A profile (name/part_id + finish) is a raw extrusion SKU — the same
     * part_id in two different finishes is physically different stock and
     * can't be cut from one another, so identity within a job is
     * (cut_job_id, name, finish, dimension, description). Description ("use",
     * e.g. Head/Sill/Jamb) is part of that identity on purpose — a head and
     * a sill of the same profile can easily land on the same dimension, and
     * without it they'd merge into one row, so the printed label would show
     * one piece's use on both pieces' stickers.
     */
    public function up(): void
    {
        Schema::connection('cutflow')->create('parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cut_job_id')->constrained('cut_jobs')->cascadeOnDelete();
            $table->string('name');
            $table->string('finish')->nullable();
            $table->decimal('dimension_inches', 8, 3);
            $table->unsignedInteger('qty_original')->default(0);
            $table->unsignedInteger('qty_remaining')->default(0);
            $table->string('work_order')->nullable();
            $table->string('phase')->nullable();
            $table->string('description')->nullable();
            $table->integer('row')->nullable();
            $table->integer('column')->nullable();
            $table->decimal('left_cut_angle', 5, 2)->nullable();
            $table->decimal('right_cut_angle', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(
                ['cut_job_id', 'name', 'finish', 'dimension_inches', 'description'],
                'parts_job_profile_dimension_use_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->dropIfExists('parts');
    }
};
