<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cut-list line is now identified by its elevation (phase) and opening position (row/column)
 * as well as profile, finish, length and use, so the same part in two elevations stays two lines
 * instead of being merged under whichever elevation was read last.
 */
return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->table('parts', function (Blueprint $table) {
            $table->dropUnique('parts_job_profile_dimension_use_unique');
            $table->unique(
                ['cut_job_id', 'name', 'finish', 'dimension_inches', 'description', 'phase', 'row', 'column'],
                'parts_job_line_identity_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->table('parts', function (Blueprint $table) {
            $table->dropUnique('parts_job_line_identity_unique');
            $table->unique(
                ['cut_job_id', 'name', 'finish', 'dimension_inches', 'description'],
                'parts_job_profile_dimension_use_unique'
            );
        });
    }
};
