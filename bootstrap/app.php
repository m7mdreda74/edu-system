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
        // Authenticated parents, teachers, and admins must not be sent to the
        // student-only /dashboard when they revisit a guest page such as
        // /register. That otherwise becomes a misleading 403 page.
        \Illuminate\Auth\Middleware\RedirectIfAuthenticated::redirectUsing(
            function (Request $request): string {
                $user = $request->user();

                if ($user?->isAdmin()) {
                    return route('admin.dashboard');
                }

                if ($user?->isTeacher()) {
                    return route('teacher.dashboard');
                }

                if ($user?->isParent()) {
                    return route('parent.dashboard');
                }

                return route('dashboard');
            },
        );

        // Trust Vercel's proxy so Laravel generates HTTPS URLs
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            \App\Http\Middleware\AddSecurityHeaders::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\LimitConcurrentSessions::class,
        ]);

        // ─── API Rate Limiting ─────────────────────────────────────────────
        // Protect progress update + quiz submit from abuse
        $middleware->throttleApi();

        // ─── Aliases ──────────────────────────────────────────────────────
        $middleware->alias([
            'role'       => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'active'     => \App\Http\Middleware\EnsureUserIsActive::class,
            'cron.secret' => \App\Http\Middleware\VerifyCronSecret::class,
            'admin.sensitive' => \App\Http\Middleware\RequireSensitiveAdminPassword::class,
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
