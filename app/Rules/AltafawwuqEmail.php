<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AltafawwuqEmail implements ValidationRule
{
    public const DOMAIN = 'altafawwuq.com';

    public const SUFFIX = '@' . self::DOMAIN;

    public static function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! str_ends_with(self::normalize(is_string($value) ? $value : null), self::SUFFIX)) {
            $fail('يجب استخدام بريد إلكتروني ينتهي بـ @altafawwuq.com.');
        }
    }
}
