<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->table('cut_log_entries', function (Blueprint $table) {
            // A planned piece outside the TigerStop's travel limits, cut by hand
            // without moving the stop. Consumed from stock like any other cut.
            $table->boolean('is_manual_cut')->default(false)->after('is_reprint');
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->table('cut_log_entries', function (Blueprint $table) {
            $table->dropColumn('is_manual_cut');
        });
    }
};
