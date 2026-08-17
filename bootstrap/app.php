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
            // A beérkező webhookok külön csoportban futnak: nincs session,
            // nincs CSRF, csak sebesség-korlát.
            Route::middleware('ingest')
                ->group(base_path('routes/ingest.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('ingest', [
            'throttle:ingest',
        ]);

        $middleware->redirectGuestsTo('/login');

        // Reverse proxy (nginx-proxy-manager / Cloudflare) mögött futunk.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
