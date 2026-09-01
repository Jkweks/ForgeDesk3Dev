<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prune expired Sanctum tokens daily (keeps personal_access_tokens table tidy)
Schedule::command('sanctum:prune-expired --hours=720')->daily();

// Lock accounts whose temporary password was never changed within the window
Schedule::command('users:expire-temp-passwords')->hourly();
