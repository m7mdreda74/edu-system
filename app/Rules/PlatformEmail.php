<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PlatformEmail implements ValidationRule
{
    public const DOMAIN = 'almagd.com';

    public const SUFFIX = '@' . self::DOMAIN;

    public const LEGACY_DOMAIN = 'altafawwuq.com';

    public const LEGACY_SUFFIX = '@' . self::LEGACY_DOMAIN;

    public function __construct(private readonly bool $allowLegacy = false)
    {
    }

    public static function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = self::normalize(is_string($value) ? $value : null);
        $allowedSuffixes = $this->allowLegacy
            ? [self::SUFFIX, self::LEGACY_SUFFIX]
            : [self::SUFFIX];

        foreach ($allowedSuffixes as $suffix) {
            if (str_ends_with($email, $suffix)) {
                return;
            }
        }

        $fail('يجب استخدام بريد إلكتروني ينتهي بـ @almagd.com.');
    }
}
