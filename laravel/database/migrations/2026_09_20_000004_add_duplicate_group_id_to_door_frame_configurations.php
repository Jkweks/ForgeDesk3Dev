<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->uuid('duplicate_group_id')->nullable()->after('quantity')->index();
        });
    }

    public function down(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->dropColumn('duplicate_group_id');
        });
    }
};
