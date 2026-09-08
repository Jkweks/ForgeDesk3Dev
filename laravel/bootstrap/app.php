<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\NormalizeApiErrorResponse::class,
        ]);

        // Public, pre-authentication endpoints: the caller has no session or
        // XSRF cookie yet, so Sanctum's stateful CSRF check only ever 419s them.
        // Throttling (see routes/api.php) is the real protection here.
        $middleware->validateCsrfTokens(except: [
            'api/password/forgot',
            'api/password/reset',
            'api/password/verify-token',
        ]);
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'password.current' => \App\Http\Middleware\EnsurePasswordIsCurrent::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Prune expired Sanctum tokens daily at 2 AM
        $schedule->command('sanctum:prune-expired --hours=24')->daily()->at('02:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
