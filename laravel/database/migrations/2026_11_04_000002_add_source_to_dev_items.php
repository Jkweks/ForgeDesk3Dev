<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dev_items', function (Blueprint $table) {
            $table->string('source', 20)->default('web')->after('type'); // web | field_app
        });
    }

    public function down(): void
    {
        Schema::table('dev_items', fn (Blueprint $table) => $table->dropColumn('source'));
    }
};
