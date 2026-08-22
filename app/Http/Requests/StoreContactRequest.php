<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => trim((string) $this->input('email')),
            'phone' => PhoneNumber::normalize((string) $this->input('phone')),
            'message' => trim((string) $this->input('message')),
            'captcha_token' => trim((string) $this->input('captcha_token')),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:25',
                'regex:/^(?:(?:\+|00)[1-9][0-9]{6,14}|0[1-9][0-9]{6,14}|[1-9][0-9]{6,14})$/',
            ],
            'message' => ['required', 'string', 'max:5000'],
            'captcha_token' => ['required', 'string', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $message = trim((string) $this->input('message'));
            $wordCount = preg_match_all('/\S+/u', $message);

            if ($message !== '' && ($wordCount === false || $wordCount < 2)) {
                $validator->errors()->add('message', 'اكتب الرسالة في كلمتين على الأقل.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'يرجى إدخال الاسم.',
            'name.max' => 'الاسم يجب ألا يتجاوز 100 حرف.',
            'email.email' => 'يرجى إدخال بريد إلكتروني صحيح.',
            'phone.required' => 'يرجى إدخال رقم الهاتف.',
            'phone.max' => 'رقم الهاتف طويل بشكل غير صحيح.',
            'phone.regex' => 'أدخل رقم هاتف صحيحًا مع مفتاح الدولة عند الحاجة.',
            'message.required' => 'يرجى كتابة تفاصيل الاستفسار.',
            'message.max' => 'تفاصيل الاستفسار يجب ألا تتجاوز 5000 حرف.',
            'captcha_token.required' => 'يرجى إكمال التحقق الأمني.',
            'captcha_token.max' => 'رمز التحقق الأمني غير صالح.',
        ];
    }

}
