<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Require a recent password confirmation before mutating admin state in production. */
final class RequireSensitiveAdminPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || config('app.env') !== 'production') {
            return $next($request);
        }

        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);
        $timeout = max(60, (int) config('auth.sensitive_admin_password_timeout', 900));

        if ($confirmedAt > 0 && now()->timestamp - $confirmedAt <= $timeout) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Password confirmation required before this administrative action.',
                'redirect' => route('password.confirm'),
            ], 423);
        }

        return redirect()->guest(route('password.confirm'));
    }
}
