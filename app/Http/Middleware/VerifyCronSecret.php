<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCronSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.vercel.cron_secret', '');
        $provided = (string) $request->header('Authorization', '');

        abort_if(
            $secret === '' || ! hash_equals('Bearer '.$secret, $provided),
            401,
            'Unauthorized',
        );

        return $next($request);
    }
}
