<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust Vercel's proxy so Laravel generates HTTPS URLs
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\LimitConcurrentSessions::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        // ─── API Rate Limiting ─────────────────────────────────────────────
        // Protect progress update + quiz submit from abuse
        $middleware->throttleApi();

        // ─── Aliases ──────────────────────────────────────────────────────
        $middleware->alias([
            'role'       => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ─── Inertia-friendly exception rendering ──────────────────────────
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($request->header('X-Inertia')) {
                return inertia('Error', [
                    'status' => $e->getStatusCode(),
                ])->toResponse($request)->setStatusCode($e->getStatusCode());
            }
        });
    })->create();
