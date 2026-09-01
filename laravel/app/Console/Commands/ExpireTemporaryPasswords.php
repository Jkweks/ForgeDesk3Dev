<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Deactivates any account still sitting on an unchanged temporary password past
 * the allowed window. The login route already refuses these users; this makes
 * the lock-out visible in the admin user list and enforces it for any other
 * entry point. An admin clears it with "Resend invitation".
 */
class ExpireTemporaryPasswords extends Command
{
    protected $signature = 'users:expire-temp-passwords';

    protected $description = 'Lock accounts whose temporary password was not changed within the allowed window';

    public function handle(): int
    {
        $cutoff = now()->subHours((int) config('auth.temp_password.ttl_hours', 48));

        $stale = User::query()
            ->where('must_change_password', true)
            ->where('is_active', true)
            ->whereNotNull('password_set_at')
            ->where('password_set_at', '<', $cutoff)
            ->get();

        foreach ($stale as $user) {
            $user->update(['is_active' => false]);
            $user->tokens()->delete();
            $this->line("Locked {$user->email} — temporary password expired.");
        }

        $this->info("Locked {$stale->count()} account(s) with an expired temporary password.");

        return self::SUCCESS;
    }
}
