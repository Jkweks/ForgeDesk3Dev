<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust X-Forwarded-For/Port/Proto (needed for correct client IP and
        // https:// scheme detection behind the reverse proxy) but NOT
        // X-Forwarded-Host: the upstream proxy doesn't reliably send that
        // header, and when it's present-but-empty Symfony trusts it as an
        // empty string, which makes every url()/asset() call resolve to a
        // hostless "https:/path" (no double slash, no host) — the actual
        // Host header nginx passes through from the real request is already
        // correct and doesn't need overriding here.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
        );
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
