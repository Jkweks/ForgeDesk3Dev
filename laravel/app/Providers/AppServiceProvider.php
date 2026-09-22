<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // CutFlow's migrations live on the 'cutflow' DB connection (each sets
        // protected $connection = 'cutflow'), separate from this app's own
        // migrations directory so `php artisan migrate` still runs both in
        // one command.
        $this->loadMigrationsFrom(database_path('migrations/cutflow'));
    }
}
