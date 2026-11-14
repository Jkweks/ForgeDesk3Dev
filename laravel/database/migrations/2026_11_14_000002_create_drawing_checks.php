<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic error checking for imported shop drawings (ported from the PM Suite): does each frame close
 * (frame size = openings + members), what did a person decide about the ones that do not, review status per
 * elevation, and progress for long reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drawing_axis_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drawing_elevation_id')->constrained()->cascadeOnDelete();
            $table->string('axis', 1);                                   // w | h
            $table->boolean('ok')->nullable();                           // null = cannot be judged (no frame size, nothing in it, or a multi-sheet run)
            $table->decimal('member', 8, 4)->nullable();                 // the uniform member that makes it close
            $table->unsignedInteger('used')->default(0);
            $table->unsignedInteger('dropped')->default(0);              // readings left out to make it close
            $table->unsignedInteger('chains')->default(0);
            $table->boolean('printed')->default(false);                  // closed on the members the sheet prints (expansion mullion)
            $table->boolean('edited')->default(false);                   // a person changed a value on this axis; `ok` was re-checked
            $table->string('resolution', 12)->nullable();                // unverified | accepted (only meaningful while ok = false)
            $table->text('resolution_reason')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->unique(['drawing_elevation_id', 'axis']);
        });

        Schema::create('drawing_printed_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drawing_elevation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row');                              // one row of members the sheet prints under a width chain
            $table->unsignedInteger('position');
            $table->decimal('value', 8, 4);
        });

        Schema::table('drawing_elevations', function (Blueprint $table) {
            $table->string('review_status', 10)->default('pending')->after('door_h');   // pending | approved | excluded
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('multi_sheet')->default(false);
            $table->string('manual_takeoff')->nullable();                // e.g. "mirror: IG-3" (not sized by the app)
        });

        Schema::table('drawing_sets', function (Blueprint $table) {
            $table->unsignedInteger('progress_done')->default(0);
            $table->unsignedInteger('progress_count')->default(0);
            $table->string('progress_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('drawing_sets', fn (Blueprint $t) => $t->dropColumn(['progress_done', 'progress_count', 'progress_note']));
        Schema::table('drawing_elevations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reviewed_by');
            $t->dropColumn(['review_status', 'reviewed_at', 'multi_sheet', 'manual_takeoff']);
        });
        Schema::dropIfExists('drawing_printed_members');
        Schema::dropIfExists('drawing_axis_checks');
    }
};
