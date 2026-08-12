<?php

declare(strict_types=1);

use App\Application\Learning\Services\JitsiMeetingTokenService;
use App\Domain\User\Models\User;

it('returns no token when the Jitsi deployment is anonymous', function (): void {
    config()->set('services.jitsi.app_id', null);
    config()->set('services.jitsi.app_secret', null);

    $user = new User(['name' => 'طالب', 'email' => 'student@example.test']);
    $user->id = 7;

    expect(app(JitsiMeetingTokenService::class)->issue($user, false, 'room-7', 'meet.jit.si'))->toBeNull();
});

it('creates a signed, room-scoped Jitsi token when JWT credentials are configured', function (): void {
    config()->set('services.jitsi.app_id', 'altafawwuq');
    config()->set('services.jitsi.app_secret', 'test-jitsi-secret');
    config()->set('services.jitsi.token_ttl', 3600);

    $user = new User(['name' => 'المعلم', 'email' => 'teacher@example.test']);
    $user->id = 42;

    $token = app(JitsiMeetingTokenService::class)->issue(
        $user,
        true,
        'altafawwuq-99-secure-room',
        'meet.example.test',
    );

    [$header, $payload, $signature] = explode('.', $token);
    $decode = static function (string $value): array {
        $padded = $value.str_repeat('=', (4 - strlen($value) % 4) % 4);

        return json_decode(
            base64_decode(strtr($padded, '-_', '+/'), true),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    };

    $claims = $decode($payload);
    $expectedSignature = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", 'test-jitsi-secret', true)), '+/', '-_'), '=');

    expect($decode($header))->toMatchArray(['alg' => 'HS256', 'typ' => 'JWT'])
        ->and($claims)->toMatchArray([
            'aud' => 'altafawwuq',
            'iss' => 'altafawwuq',
            'sub' => 'meet.example.test',
            'room' => 'altafawwuq-99-secure-room',
        ])
        ->and($claims['context']['user'])->toMatchArray([
            'id' => '42',
            'name' => 'المعلم',
            'email' => 'teacher@example.test',
            'moderator' => true,
        ])
        ->and($claims['exp'] - $claims['nbf'])->toBe(3630)
        ->and($signature)->toBe($expectedSignature);
});
