<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consumption is per CUT, as a fraction of the product's STOCK length (cut inches / stock length) — the
 * same whether the piece comes off a full stick or a drop, since drops are remnants of stock. Replaces
 * the per-stick ledger from the previous migration (never released).
 */
return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->dropIfExists('cut_consumptions');
        if (Schema::connection('cutflow')->hasColumn('stick_sessions', 'consumed_at')) {
            Schema::connection('cutflow')->table('stick_sessions', fn (Blueprint $t) => $t->dropColumn('consumed_at'));
        }

        Schema::connection('cutflow')->create('cut_consumptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cut_log_entry_id')->unique();
            $table->unsignedBigInteger('cut_job_id');
            $table->unsignedBigInteger('product_id'); // products.id on the default connection (no cross-database FK)
            $table->decimal('stock_fraction', 10, 4); // cut inches / the product's stock length
            $table->timestamps();

            $table->index(['cut_job_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->dropIfExists('cut_consumptions');
    }
};
