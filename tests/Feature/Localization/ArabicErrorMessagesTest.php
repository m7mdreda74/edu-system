<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ArabicErrorMessagesTest extends TestCase
{
    public function test_framework_validation_messages_are_arabic_and_use_friendly_attributes(): void
    {
        app()->setLocale('ar');

        $validator = Validator::make([], [
            'email' => ['required'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $this->assertSame('حقل البريد الإلكتروني مطلوب.', $validator->errors()->first('email'));
        $this->assertSame('حقل كلمة المرور مطلوب.', $validator->errors()->first('password'));

        $validator = Validator::make(['password' => '123'], [
            'password' => ['string', 'min:8'],
        ]);

        $this->assertSame('يجب ألا يقل طول حقل كلمة المرور عن 8 حرفًا.', $validator->errors()->first('password'));
    }

    public function test_authentication_and_password_reset_messages_are_arabic(): void
    {
        app()->setLocale('ar');

        $this->assertSame('كلمة المرور المدخلة غير صحيحة.', __('auth.password'));
        $this->assertSame('رابط إعادة تعيين كلمة المرور غير صالح أو منتهي.', __('passwords.token'));
    }
}
