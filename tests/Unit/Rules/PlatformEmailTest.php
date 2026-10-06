<?php

declare(strict_types=1);

use App\Rules\PlatformEmail;
use Illuminate\Support\Facades\Validator;

it('accepts the new platform email domain for new accounts', function (): void {
    expect(Validator::make(
        ['email' => 'student@almagd.com'],
        ['email' => [new PlatformEmail()]],
    )->passes())->toBeTrue();
});

it('rejects the legacy domain for new accounts', function (): void {
    expect(Validator::make(
        ['email' => 'student@altafawwuq.com'],
        ['email' => [new PlatformEmail()]],
    )->passes())->toBeFalse();
});

it('can keep legacy emails valid while an existing profile is migrated', function (): void {
    expect(Validator::make(
        ['email' => 'student@altafawwuq.com'],
        ['email' => [new PlatformEmail(allowLegacy: true)]],
    )->passes())->toBeTrue();
});
