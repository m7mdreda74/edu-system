<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Every rule is `sometimes` so each slot in the lesson row can save on its own:
 * dropping a video link posts `video_url` alone and leaves the rest untouched.
 */
class UpdateCurriculumLessonRequest extends FormRequest
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
            'title'            => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'video_url'        => ['sometimes', 'nullable', 'url', 'max:2048'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:86400'],
            'description'      => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_free_preview'  => ['sometimes', 'boolean'],
            'order'            => ['sometimes', 'integer', 'min:1', 'max:999'],
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
            'order.min'            => 'ترتيب الدرس يجب أن يبدأ من 1.',
        ];
    }
}
