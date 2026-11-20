<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// An elevation delivered outside the shipment system (before shipments were tracked here, or by other means).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_wo_elevations', function (Blueprint $table) {
            $table->date('previously_delivered_on')->nullable();
            $table->string('previously_delivered_note', 1000)->nullable();
            $table->unsignedBigInteger('previously_delivered_by')->nullable();
            $table->timestamp('previously_delivered_marked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fd_wo_elevations', function (Blueprint $table) {
            $table->dropColumn(['previously_delivered_on', 'previously_delivered_note', 'previously_delivered_by', 'previously_delivered_marked_at']);
        });
    }
};
