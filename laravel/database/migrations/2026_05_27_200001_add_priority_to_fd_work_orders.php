<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->unsignedInteger('priority')->nullable()->after('archived');
        });

        // Seed existing non-archived WOs with sequential priorities in creation order
        $ids = DB::table('fd_work_orders')
            ->where('archived', false)
            ->orderBy('created_at')
            ->pluck('id');

        foreach ($ids as $i => $id) {
            DB::table('fd_work_orders')->where('id', $id)->update(['priority' => $i + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
