<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The set's own glazing schedule (cover sheet) and what was left out, from the import. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drawing_sets', function (Blueprint $table) {
            $table->text('skip_summary')->nullable();
        });
        Schema::create('drawing_glazing_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drawing_set_id')->constrained()->cascadeOnDelete();
            $table->string('tag', 40);
            $table->text('makeup')->nullable();      // the glass make-up lines, one per line
            $table->unique(['drawing_set_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drawing_glazing_tags');
        Schema::table('drawing_sets', fn (Blueprint $t) => $t->dropColumn('skip_summary'));
    }
};
