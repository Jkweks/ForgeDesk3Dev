<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Door tags become the configuration's identity (configuration_name is
 * dropped), and quantity becomes derived from the number of door tags
 * rather than a manually-entered value — backfill existing rows to match
 * before removing the ability to diverge.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE door_frame_configurations c
            SET quantity = sub.tag_count
            FROM (
                SELECT configuration_id, COUNT(*) AS tag_count
                FROM door_frame_configuration_doors
                GROUP BY configuration_id
            ) sub
            WHERE sub.configuration_id = c.id
            AND c.quantity != sub.tag_count
        ');

        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->dropColumn('configuration_name');
        });
    }

    public function down(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->string('configuration_name')->nullable()->after('business_job_id');
        });
    }
};
