<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-sheet runs: each sheet of a long elevation draws a little of its neighbours for context and marks where
 * its own part ends with numbered break (match) lines. A sheet owns what is drawn wholly between its lines.
 * `drawing_sheet_breaks` stores the lines; `drawing_dim_marks.counted` says whether a reading is inside them
 * (false = shown for context, counted on the sheet that draws it whole).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drawing_sheet_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drawing_sheet_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('num')->nullable();   // "BREAK LINE 2"; null when the label has no number
            $table->decimal('x', 10, 2);                  // where the line sits, in page points from the left
            $table->index('drawing_sheet_id');
        });

        Schema::table('drawing_dim_marks', function (Blueprint $table) {
            $table->boolean('counted')->default(true)->after('bottom');
        });
    }

    public function down(): void
    {
        Schema::table('drawing_dim_marks', function (Blueprint $table) {
            $table->dropColumn('counted');
        });
        Schema::dropIfExists('drawing_sheet_breaks');
    }
};
