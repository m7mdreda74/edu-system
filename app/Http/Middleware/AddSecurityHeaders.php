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
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        $jitsiOrigin = $this->jitsiOrigin();

        $response->headers->set(
            'Permissions-Policy',
            sprintf('camera=(self "%s"), microphone=(self "%s"), geolocation=()', $jitsiOrigin, $jitsiOrigin),
        );
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->remove('X-Powered-By');

        // PHP can add this header after the framework response is prepared.
        // Remove it where the active SAPI exposes header_remove as well.
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }

    private function jitsiOrigin(): string
    {
        $domain = trim((string) config('services.jitsi.domain', 'meet.jit.si'));

        return str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')
            ? rtrim($domain, '/')
            : 'https://' . trim($domain, '/');
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $jitsiOrigin = $this->jitsiOrigin();

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "script-src 'self' {$jitsiOrigin} https://challenges.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "media-src 'self' blob: https:",
            "connect-src 'self' {$jitsiOrigin} wss://{$this->jitsiHost()} https://challenges.cloudflare.com https://*.vercel-storage.com",
            "frame-src 'self' {$jitsiOrigin} https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com",
            "child-src 'self' {$jitsiOrigin} https://www.youtube-nocookie.com https://player.vimeo.com",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
        ];

        if ($request->isSecure() && app()->environment('production')) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    private function jitsiHost(): string
    {
        $origin = $this->jitsiOrigin();
        $host = parse_url($origin, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'meet.jit.si';
    }
}
