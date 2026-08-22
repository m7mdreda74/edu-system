<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Only the title is required — a lesson starts as an empty row and the teacher
 * fills the video, the booklet and the homework into it afterwards.
 */
class StoreCurriculumLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->isTeacher() ?? false;
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'min:3', 'max:255'],
            'video_url'        => ['nullable', 'url', 'max:2048'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'is_free_preview'  => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'       => 'عنوان الدرس مطلوب.',
            'title.max'            => 'عنوان الدرس يجب ألا يتجاوز 255 حرفاً.',
            'video_url.url'        => 'رابط فيديو الشرح غير صحيح.',
            'duration_seconds.max' => 'مدة الدرس يجب ألا تتجاوز 24 ساعة.',
            'description.max'      => 'وصف الدرس يجب ألا يتجاوز 2000 حرف.',
        ];
    }
}
