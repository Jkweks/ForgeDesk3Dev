<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let an admin create accounts without immediately emailing the invitation, so
 * profile / role / permission setup can happen first, then send the held
 * invitations in bulk.
 *
 * `welcome_email_sent_at` null  => invitation still held (not sent)
 *                        set   => invitation delivered at that time
 *
 * Existing users are backfilled to `created_at` so they never look "not invited"
 * and a bulk send can't blast them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('welcome_email_sent_at')->nullable()->after('password_set_at');
        });

        DB::table('users')->whereNull('welcome_email_sent_at')->update([
            'welcome_email_sent_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('welcome_email_sent_at'));
    }
};
