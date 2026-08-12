<?php

declare(strict_types=1);

namespace App\Application\Learning\Services;

use App\Domain\User\Models\User;

/**
 * Creates a short-lived room-scoped JWT for a Jitsi deployment configured
 * with token authentication. Local anonymous rooms work without it only when
 * room authentication is explicitly disabled.
 */
final class JitsiMeetingTokenService
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.jitsi.app_id')) !== ''
            && trim((string) config('services.jitsi.app_secret')) !== '';
    }

    public function issue(User $user, bool $isTeacher, string $roomName, string $domain): ?string
    {
        $appId = trim((string) config('services.jitsi.app_id'));
        $appSecret = trim((string) config('services.jitsi.app_secret'));

        if (! $this->isConfigured()) {
            return null;
        }

        $now = now()->getTimestamp();
        $ttl = max(300, (int) config('services.jitsi.token_ttl', 21600));
        $payload = [
            'aud' => $appId,
            'iss' => $appId,
            'sub' => $domain,
            'room' => $roomName,
            'nbf' => $now - 30,
            'exp' => $now + $ttl,
            'context' => [
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'moderator' => $isTeacher,
                ],
            ],
        ];

        $header = $this->encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $body = $this->encode($payload);
        $signature = $this->encode(hash_hmac('sha256', "{$header}.{$body}", $appSecret, true));

        return "{$header}.{$body}.{$signature}";
    }

    /** @param array<string, mixed> $value */
    private function encode(array|string $value): string
    {
        $encoded = is_array($value)
            ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            : $value;

        return rtrim(strtr(base64_encode($encoded), '+/', '-_'), '=');
    }
}
