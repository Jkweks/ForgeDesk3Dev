<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A finished stick is one stick pulled from inventory. stick_sessions.consumed_at marks that it has been
 * accounted for (so it is deducted exactly once); cut_consumptions records each cut list's share of
 * that stick (shares of one stick sum to 1) — the ledger a job's reservation consumption is summed from,
 * so rounding never accumulates.
 */
return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->table('stick_sessions', function (Blueprint $table) {
            $table->timestamp('consumed_at')->nullable();
        });

        Schema::connection('cutflow')->create('cut_consumptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stick_session_id');
            $table->unsignedBigInteger('cut_job_id');
            $table->unsignedBigInteger('product_id'); // products.id on the default connection (no cross-database FK)
            $table->decimal('share', 8, 4);
            $table->timestamps();

            $table->unique(['stick_session_id', 'cut_job_id']);
            $table->index(['cut_job_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->dropIfExists('cut_consumptions');
        Schema::connection('cutflow')->table('stick_sessions', function (Blueprint $table) {
            $table->dropColumn('consumed_at');
        });
    }
};
