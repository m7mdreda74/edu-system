<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\Learning\Models\Worksheet;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * "الواجب" attached to a lesson. Same open-format policy as the booklet.
 *
 * The file is only required the first time: once homework exists the teacher
 * comes back to this endpoint to move the due date or change the score without
 * re-uploading the same sheet.
 */
class UploadHomeworkRequest extends FormRequest
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
            'file'                => [$this->lessonHasHomework() ? 'nullable' : 'required', 'file', 'max:' . UploadBookletRequest::MAX_KILOBYTES],
            'title'               => ['nullable', 'string', 'max:255'],
            'due_date'            => ['nullable', 'date'],
            'max_score'           => ['nullable', 'integer', 'min:1', 'max:1000'],
            'requires_submission' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required'  => 'اختر ملف الواجب أولاً.',
            'file.file'      => 'الواجب يجب أن يكون ملفاً.',
            'file.max'       => 'حجم الواجب يجب ألا يتجاوز 25 ميجابايت.',
            'title.max'      => 'عنوان الواجب يجب ألا يتجاوز 255 حرفاً.',
            'due_date.date'  => 'تاريخ تسليم الواجب غير صحيح.',
            'max_score.min'  => 'الدرجة النهائية للواجب يجب أن تكون 1 على الأقل.',
            'max_score.max'  => 'الدرجة النهائية للواجب يجب ألا تتجاوز 1000.',
        ];
    }

    private function lessonHasHomework(): bool
    {
        return Worksheet::where('lesson_id', $this->route('lesson'))
            ->where('type', Worksheet::TYPE_HOMEWORK)
            ->exists();
    }
}
