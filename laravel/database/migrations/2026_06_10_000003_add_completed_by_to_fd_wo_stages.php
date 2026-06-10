<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_wo_stages', function (Blueprint $table) {
            $table->unsignedBigInteger('completed_by_id')->nullable()->after('assigned_to_id');
            $table->foreign('completed_by_id')->references('id')->on('fd_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fd_wo_stages', function (Blueprint $table) {
            $table->dropForeign(['completed_by_id']);
            $table->dropColumn('completed_by_id');
        });
    }
};
