<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->table('cutflow_settings', function (Blueprint $table) {
            // Off for runs of parts with all finished surfaces, where a label would only mar the part.
            $table->boolean('printer_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->table('cutflow_settings', function (Blueprint $table) {
            $table->dropColumn('printer_enabled');
        });
    }
};
