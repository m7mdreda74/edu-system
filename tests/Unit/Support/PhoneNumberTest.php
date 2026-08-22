<?php

declare(strict_types=1);

use App\Support\PhoneNumber;

it('normalizes Arabic digits and common phone separators', function (): void {
    expect(PhoneNumber::normalize('٠١٠ ١٢٣٤-٥٦٧٨'))->toBe('01012345678')
        ->and(PhoneNumber::normalize('0020 (10) 1234-5678'))->toBe('+201012345678');
});

it('accepts supported phone lengths and rejects malformed values', function (): void {
    expect(PhoneNumber::isValid('50000009'))->toBeTrue()
        ->and(PhoneNumber::isValid('+201012345678'))->toBeTrue()
        ->and(PhoneNumber::isValid('123456'))->toBeFalse()
        ->and(PhoneNumber::isValid('01012abc789'))->toBeFalse();
});
