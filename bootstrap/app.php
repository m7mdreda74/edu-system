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
        $localizedHttpMessage = static function (int $status, string $message = ''): string {
            $message = trim($message);
            $genericMessages = [
                '',
                'Bad Request',
                'Forbidden',
                'Internal Server Error',
                'Not Found',
                'The given data was invalid.',
                'This action is unauthorized.',
                'Too Many Requests',
                'Unauthorized',
                'Unprocessable Entity',
                'Service Unavailable',
            ];

            if (! in_array($message, $genericMessages, true)) {
                return $message;
            }

            return match ($status) {
                400 => 'الطلب المرسل غير صالح.',
                401 => 'يجب تسجيل الدخول أولًا للمتابعة.',
                403 => 'غير مصرح لك بتنفيذ هذا الإجراء.',
                404 => 'العنصر المطلوب غير موجود.',
                405 => 'طريقة الطلب غير مسموحة.',
                419 => 'انتهت جلسة الصفحة. حدّث الصفحة وحاول مرة أخرى.',
                422 => 'البيانات المرسلة غير صالحة. راجع الحقول وحاول مرة أخرى.',
                429 => 'تم تجاوز عدد المحاولات. انتظر قليلًا ثم حاول مرة أخرى.',
                423 => 'يجب تأكيد كلمة المرور قبل تنفيذ هذا الإجراء.',
                500 => 'حدث خطأ غير متوقع في الخادم. حاول مرة أخرى لاحقًا.',
                503 => 'الخدمة غير متاحة حاليًا. حاول مرة أخرى بعد قليل.',
                default => 'حدث خطأ أثناء تنفيذ الطلب.',
            };
        };

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'يجب تسجيل الدخول أولًا للمتابعة.',
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) use ($localizedHttpMessage) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $localizedHttpMessage(403, $e->getMessage()),
                ], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'العنصر المطلوب غير موجود.',
                ], 404);
            }
        });

        // ─── Inertia-friendly exception rendering ──────────────────────────
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) use ($localizedHttpMessage) {
            if ($request->header('X-Inertia')) {
                return inertia('Error', [
                    'status' => $e->getStatusCode(),
                ])->toResponse($request)->setStatusCode($e->getStatusCode());
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $localizedHttpMessage($e->getStatusCode(), $e->getMessage()),
                ], $e->getStatusCode(), $e->getHeaders());
            }
        });

        // Never expose raw exception text from image processing, payment
        // gateways, storage providers, or other infrastructure to the client.
        // Those messages remain available in logs while users receive a stable
        // Arabic response appropriate for the request type.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (
                $e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
            ) {
                return null;
            }

            if ($request->header('X-Inertia')) {
                return inertia('Error', [
                    'status' => 500,
                ])->toResponse($request)->setStatusCode(500);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'حدث خطأ غير متوقع في الخادم. حاول مرة أخرى لاحقًا.',
                ], 500);
            }
        });
    })->create();
