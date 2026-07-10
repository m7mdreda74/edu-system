<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('teacher') ?? false;
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'min:3', 'max:255'],
            'video_url'        => ['required', 'url', 'max:500'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
            'is_free_preview'  => ['boolean'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'attachment_path'  => ['nullable', 'file', 'max:10240', 'mimes:pdf,zip,doc,docx'],
        ];
    }

    public function messages(): array
    {
        return [
            'video_url.url'               => 'رابط الفيديو غير صحيح.',
            'duration_seconds.min'        => 'مدة الدرس يجب أن تكون على الأقل ثانية واحدة.',
            'attachment_path.max'         => 'حجم الملف يجب ألا يتجاوز 10MB.',
        ];
    }
}
