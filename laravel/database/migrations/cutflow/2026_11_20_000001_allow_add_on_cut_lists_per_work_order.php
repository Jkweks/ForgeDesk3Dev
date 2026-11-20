<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A work order can own several cut lists: sequence 1 is the main list, 2+ are "add-on" lists
// created when openings are released after the work order was completed.
return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->table('cut_jobs', function (Blueprint $table) {
            $table->dropUnique(['work_order_id']);
            $table->unsignedSmallInteger('sequence')->default(1)->after('work_order_id');
        });
        Schema::connection('cutflow')->table('cut_jobs', function (Blueprint $table) {
            $table->unique(['work_order_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->table('cut_jobs', function (Blueprint $table) {
            $table->dropUnique(['work_order_id', 'sequence']);
            $table->dropColumn('sequence');
            $table->unique('work_order_id');
        });
    }
};
