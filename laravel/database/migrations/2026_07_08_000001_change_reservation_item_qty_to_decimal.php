<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_reservation_items', function (Blueprint $table) {
            $table->decimal('requested_qty', 10, 1)->change();
            $table->decimal('committed_qty', 10, 1)->change();
            $table->decimal('consumed_qty', 10, 1)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_reservation_items', function (Blueprint $table) {
            $table->integer('requested_qty')->change();
            $table->integer('committed_qty')->change();
            $table->integer('consumed_qty')->default(0)->change();
        });
    }
};
