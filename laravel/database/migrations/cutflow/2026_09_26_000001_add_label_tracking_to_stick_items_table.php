<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->table('stick_items', function (Blueprint $table) {
            // Reserved ahead of the cut being recorded, so a label printed
            // early (before the CutLogEntry exists — see Dashboard's
            // move/print/confirm split) carries a QR that resolves once the
            // entry is finally created with this same uuid.
            $table->uuid('pending_uuid')->nullable()->after('status');
            $table->timestamp('label_printed_at')->nullable()->after('pending_uuid');
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->table('stick_items', function (Blueprint $table) {
            $table->dropColumn(['pending_uuid', 'label_printed_at']);
        });
    }
};
