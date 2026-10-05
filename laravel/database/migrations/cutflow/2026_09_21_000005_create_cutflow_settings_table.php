<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row settings table for the cut station — see CutFlowSetting::current().
 */
return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->create('cutflow_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('cut_sensor_active')->default(false);
            $table->boolean('show_cut_toast')->default(true);
            $table->decimal('kerf_inches', 6, 3)->nullable();
            $table->decimal('standard_stock_length', 8, 3)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->dropIfExists('cutflow_settings');
    }
};
