<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\PhoneNumber as PhoneNumberValue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PhoneNumberValue::isValid($value)) {
            $fail('يرجى إدخال رقم هاتف صحيح من 7 إلى 15 رقمًا، مع مفتاح الدولة عند الحاجة.');
        }
    }
}
