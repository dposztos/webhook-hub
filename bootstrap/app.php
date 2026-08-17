<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Incoming webhooks run in their own group: no session, no CSRF,
            // only rate limiting.
            Route::middleware('ingest')
                ->group(base_path('routes/ingest.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('ingest', [
            'throttle:ingest',
        ]);

        $middleware->redirectGuestsTo('/login');

        // Which peers may set X-Forwarded-*. This decides both the IP stored on
        // a message and who the per-IP rate limits actually apply to, so on a
        // directly exposed instance narrow it to your proxy's address (or an
        // empty value) instead of leaving it at '*'.
        $proxies = env('TRUSTED_PROXIES', '*');

        $middleware->trustProxies(at: $proxies === '*' ? '*' : array_filter(array_map('trim', explode(',', (string) $proxies))));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
