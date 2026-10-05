<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * parts.source — where a line came from: 'configurator' (generated from released door/frame BOMs),
 * 'csv' (uploaded) or 'manual' (added by hand). Lets a rebuild replace only generated lines.
 *
 * cut_jobs.bom_diverged_at — set the moment someone hand-edits a work order's cut list (rare fixes
 * are allowed), so the configurations behind it can warn that the cut list no longer matches their BOM
 * — e.g. before re-running one after install damage.
 */
return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->table('parts', function (Blueprint $table) {
            $table->string('source', 20)->nullable();
        });
        Schema::connection('cutflow')->table('cut_jobs', function (Blueprint $table) {
            $table->timestamp('bom_diverged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->table('parts', function (Blueprint $table) {
            $table->dropColumn('source');
        });
        Schema::connection('cutflow')->table('cut_jobs', function (Blueprint $table) {
            $table->dropColumn('bom_diverged_at');
        });
    }
};
