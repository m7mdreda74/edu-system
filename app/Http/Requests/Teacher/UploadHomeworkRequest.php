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
        $alreadyExists = $this->lessonHasHomework();

        return [
            'file' => [
                $alreadyExists ? 'nullable' : 'required_without:blob_url',
                'file',
                'mimes:pdf,doc,docx,ppt,pptx,zip,png,jpg,jpeg',
                'max:'.UploadBookletRequest::MAX_KILOBYTES,
            ],
            'blob_url' => [$alreadyExists ? 'nullable' : 'required_without:file', 'nullable', 'url:https', 'max:2048'],
            'blob_pathname' => ['nullable', 'required_with:blob_url', 'string', 'max:950'],
            'title' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'max_score' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'requires_submission' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'اختر ملف الواجب أولاً.',
            'file.required_without' => 'اختر ملف الواجب أولاً.',
            'file.file' => 'الواجب يجب أن يكون ملفاً.',
            'file.max' => 'حجم الواجب يجب ألا يتجاوز 25 ميجابايت.',
            'blob_url.required_without' => 'اختر ملف الواجب أولاً.',
            'blob_url.url' => 'تعذر التحقق من ملف الواجب المرفوع.',
            'blob_pathname.required_with' => 'بيانات ملف الواجب المرفوع غير مكتملة.',
            'title.max' => 'عنوان الواجب يجب ألا يتجاوز 255 حرفاً.',
            'due_date.date' => 'تاريخ تسليم الواجب غير صحيح.',
            'max_score.min' => 'الدرجة النهائية للواجب يجب أن تكون 1 على الأقل.',
            'max_score.max' => 'الدرجة النهائية للواجب يجب ألا تتجاوز 1000.',
        ];
    }

    private function lessonHasHomework(): bool
    {
        return Worksheet::where('lesson_id', $this->route('lesson'))
            ->where('type', Worksheet::TYPE_HOMEWORK)
            ->exists();
    }
}
