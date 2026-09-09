<?php

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
        // Enable first-party SPA authentication via Sanctum cookies.
        $middleware->statefulApi();

        // Public invitation endpoints authenticate via secret_token in the URL,
        // not the SPA session — CSRF would break cross-origin local setups
        // (Vite localhost → Laragon *.test) where XSRF cookies are not shared.
        $middleware->validateCsrfTokens(except: [
            'api/doku/notification',
            'api/invitation/*',
        ]);

        // Keep default API throttling enabled.
        $middleware->throttleApi();

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
