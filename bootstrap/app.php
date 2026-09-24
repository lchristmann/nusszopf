<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind an operator's TLS-terminating reverse proxy (docs/deployment/README.md): TRUSTED_PROXIES is
        // `*` or a comma-separated list of addresses and ranges; unset, no proxy header is trusted. The
        // production template lists loopback and the private networks, never `*` (P-4, SEC-02).
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Every response, /up and /health included (P-4, SEC-06).
        $middleware->append(SecurityHeaders::class);

        // A changed password (a reset) ends every other signed-in session on its next request (P-4, SEC-10).
        $middleware->web(append: [AuthenticateSession::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
