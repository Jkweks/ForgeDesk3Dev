<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-Forge was previously signaled by the magic string
 * elevation_tag_guess === 'Pre-Forge' (elevation_id null). That collides with
 * the new behavior of always keeping the PDF's real parsed elevation guess
 * on Pre-Forge reports (so a reviewer has something to start manual entry
 * from), so Pre-Forge needs its own first-class flag. Backfills from the old
 * magic-string convention so nothing already uploaded loses its Pre-Forge
 * status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_reports', function (Blueprint $table) {
            $table->boolean('is_pre_forge')->default(false)->after('work_order_id');
        });

        DB::statement("
            UPDATE quality_reports
            SET is_pre_forge = true
            WHERE elevation_id IS NULL
              AND elevation_tag_guess = 'Pre-Forge'
        ");
    }

    public function down(): void
    {
        Schema::table('quality_reports', function (Blueprint $table) {
            $table->dropColumn('is_pre_forge');
        });
    }
};
