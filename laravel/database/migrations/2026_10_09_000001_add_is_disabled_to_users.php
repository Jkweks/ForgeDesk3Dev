<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `is_active` is "can sign in". `is_disabled` is "no longer with the company": the person is dropped from the
 * project-manager / superintendent pickers and stops getting work-order emails. An inactive-but-not-disabled user
 * has no login but is still selectable and still emailed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_disabled')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_disabled'));
    }
};
