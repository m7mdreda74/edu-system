<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

final class TurnstileVerifier
{
    public function isConfigured(): bool
    {
        return (bool) config('services.turnstile.enabled', true)
            && trim((string) config('services.turnstile.secret_key')) !== ''
            && trim((string) config('services.turnstile.site_key')) !== '';
    }

    public function verify(string $token, ?string $remoteIp = null): bool
    {
        if (! $this->isConfigured() || trim($token) === '') {
            return false;
        }

        $payload = [
            'secret' => trim((string) config('services.turnstile.secret_key')),
            'response' => trim($token),
        ];

        if ($remoteIp !== null && trim($remoteIp) !== '') {
            $payload['remoteip'] = $remoteIp;
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(max(1, min(15, (int) config('services.turnstile.timeout', 5))))
                ->post((string) config('services.turnstile.siteverify_url'), $payload);

            return $response->successful() && $response->json('success') === true;
        } catch (Throwable) {
            return false;
        }
    }
}
