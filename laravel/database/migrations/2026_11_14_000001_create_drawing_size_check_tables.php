<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visual size check for imported shop drawings (the PM Suite "Elevation Sizing" view): where each dimension
 * the parser read is printed on its sheet, so a person can see the value over the drawing and confirm or correct it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drawing_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drawing_elevation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->decimal('width', 10, 2);
            $table->decimal('height', 10, 2);
            $table->decimal('title_x0', 10, 2)->nullable();
            $table->decimal('title_top', 10, 2)->nullable();
            $table->decimal('title_x1', 10, 2)->nullable();
            $table->decimal('title_bottom', 10, 2)->nullable();
            $table->unique(['drawing_elevation_id', 'page']);
        });

        Schema::create('drawing_dim_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drawing_elevation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->string('kind', 10);                              // ro_w fs_w ro_h fs_h do_w do_h dlo_w dlo_h
            $table->unsignedInteger('idx')->default(0);              // position within its kind on that sheet, left to right / top to bottom
            $table->decimal('value', 10, 4);                         // inches, as the parser read it
            $table->decimal('x0', 10, 2);                            // footprint in page points (origin top-left)
            $table->decimal('top', 10, 2);
            $table->decimal('x1', 10, 2);
            $table->decimal('bottom', 10, 2);
            $table->decimal('confirmed_value', 10, 4)->nullable();   // what a person says it is
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->index(['drawing_elevation_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drawing_dim_marks');
        Schema::dropIfExists('drawing_sheets');
    }
};
