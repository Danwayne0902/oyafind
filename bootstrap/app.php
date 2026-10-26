<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Production terminates TLS at Caddy, so Laravel must trust the
        // forwarded scheme and client IP. An unset value keeps the default of
        // trusting nothing, which is correct for local development.
        $proxies = env('TRUSTED_PROXIES');

        if ($proxies === '*') {
            $middleware->trustProxies(at: '*');
        } elseif (is_string($proxies) && trim($proxies) !== '') {
            $middleware->trustProxies(
                at: array_map('trim', explode(',', $proxies)),
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
