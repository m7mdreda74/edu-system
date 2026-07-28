<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * "ملزمة شرح الدرس وهي ملف PDF أو Word أو أياً كان يرفعه المدرس" — the client
 * asked for no format restriction, so there is no mime whitelist here. Size is
 * the only limit; the file lands on the public disk and is served by path.
 */
class UploadBookletRequest extends FormRequest
{
    /** 25 MB, in kilobytes as Laravel's `max` rule counts files. */
    public const MAX_KILOBYTES = 25600;

    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->isTeacher() ?? false;
    }

    public function rules(): array
    {
        return [
            'booklet' => ['nullable', 'required_without:blob_url', 'file', 'max:'.self::MAX_KILOBYTES],
            'blob_url' => ['nullable', 'required_without:booklet', 'url:https', 'max:2048'],
            'blob_pathname' => ['nullable', 'required_with:blob_url', 'string', 'max:1024'],
        ];
    }

    public function messages(): array
    {
        return [
            'booklet.required' => 'اختر ملف الملزمة أولاً.',
            'booklet.required_without' => 'اختر ملف الملزمة أولاً.',
            'booklet.file' => 'الملزمة يجب أن تكون ملفاً.',
            'booklet.max' => 'حجم الملزمة يجب ألا يتجاوز 25 ميجابايت.',
            'blob_url.required_without' => 'اختر ملف الملزمة أولاً.',
            'blob_url.url' => 'تعذر التحقق من ملف الملزمة المرفوع.',
            'blob_pathname.required_with' => 'بيانات ملف الملزمة المرفوع غير مكتملة.',
        ];
    }
}
