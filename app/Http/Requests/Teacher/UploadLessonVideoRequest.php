<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/** A private explanation video uploaded by the teacher for one lesson. */
class UploadLessonVideoRequest extends FormRequest
{
    /** 512 MB, in kilobytes as Laravel's `max` rule counts files. */
    public const MAX_KILOBYTES = 524288;

    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->isTeacher() ?? false;
    }

    public function rules(): array
    {
        return [
            'video' => [
                'nullable',
                'required_without:blob_url',
                'file',
                'mimes:mp4,webm,mov,m4v',
                'max:'.self::MAX_KILOBYTES,
            ],
            'blob_url' => ['nullable', 'required_without:video', 'url:https', 'max:2048'],
            'blob_pathname' => ['nullable', 'required_with:blob_url', 'string', 'max:950'],
        ];
    }

    public function messages(): array
    {
        return [
            'video.required' => 'اختر ملف الفيديو أولاً.',
            'video.required_without' => 'اختر ملف الفيديو أولاً.',
            'video.file' => 'فيديو الشرح يجب أن يكون ملفًا.',
            'video.mimes' => 'صيغة الفيديو المسموحة هي MP4 أو WebM أو MOV أو M4V.',
            'video.max' => 'حجم الفيديو يجب ألا يتجاوز 512 ميجابايت.',
            'blob_url.required_without' => 'ارفع ملف الفيديو أولاً.',
            'blob_url.url' => 'تعذر التحقق من ملف الفيديو المرفوع.',
            'blob_pathname.required_with' => 'بيانات ملف الفيديو المرفوع غير مكتملة.',
        ];
    }
}
