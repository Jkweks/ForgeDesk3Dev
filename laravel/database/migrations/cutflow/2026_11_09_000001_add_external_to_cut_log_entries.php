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
            // Piece cut on another saw and tagged as cut afterwards in ForgeDesk (no stick, no label).
            $table->boolean('is_external')->default(false)->after('is_manual_cut');
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->table('cut_log_entries', function (Blueprint $table) {
            $table->dropColumn('is_external');
        });
    }
};
