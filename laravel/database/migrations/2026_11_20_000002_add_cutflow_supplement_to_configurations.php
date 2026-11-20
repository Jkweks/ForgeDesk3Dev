<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Which of its work order's cut lists an opening feeds: null/1 = the main list, 2+ = an add-on list
// (assigned when the opening is released after the work order was completed).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->unsignedSmallInteger('cutflow_supplement')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->dropColumn('cutflow_supplement');
        });
    }
};
