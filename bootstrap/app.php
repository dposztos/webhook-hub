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

        // We run behind a reverse proxy (nginx-proxy-manager / Cloudflare).
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
