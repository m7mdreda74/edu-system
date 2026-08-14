<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $jitsiDomain = trim((string) config('services.jitsi.domain', 'meet.jit.si'));
        $jitsiOrigin = str_starts_with($jitsiDomain, 'http://') || str_starts_with($jitsiDomain, 'https://')
            ? rtrim($jitsiDomain, '/')
            : 'https://' . trim($jitsiDomain, '/');

        $response->headers->set(
            'Permissions-Policy',
            sprintf('camera=(self "%s"), microphone=(self "%s"), geolocation=()', $jitsiOrigin, $jitsiOrigin),
        );
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }
}
