<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\Learning\Models\Worksheet;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * "اختبار الوحدة — النموذج الورقي": one file per unit, printed and answered by
 * hand. Like the booklet it takes any format the teacher has to hand, and like
 * the homework the file is only required until one exists.
 */
class UploadPaperExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->isTeacher() ?? false;
    }

    public function rules(): array
    {
        $alreadyExists = $this->unitHasPaperExam();

        return [
            'file' => [
                $alreadyExists ? 'nullable' : 'required_without:blob_url',
                'file',
                'mimes:pdf,doc,docx,ppt,pptx,zip,png,jpg,jpeg',
                'max:'.UploadBookletRequest::MAX_KILOBYTES,
            ],
            'blob_url' => [$alreadyExists ? 'nullable' : 'required_without:file', 'nullable', 'url:https', 'max:2048'],
            'blob_pathname' => ['nullable', 'required_with:blob_url', 'string', 'max:1024'],
            'title' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'max_score' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'requires_submission' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'اختر ملف النموذج الورقي أولاً.',
            'file.required_without' => 'اختر ملف النموذج الورقي أولاً.',
            'file.file' => 'النموذج الورقي يجب أن يكون ملفاً.',
            'file.max' => 'حجم النموذج الورقي يجب ألا يتجاوز 25 ميجابايت.',
            'blob_url.required_without' => 'اختر ملف النموذج الورقي أولاً.',
            'blob_url.url' => 'تعذر التحقق من ملف النموذج الورقي المرفوع.',
            'blob_pathname.required_with' => 'بيانات ملف النموذج الورقي المرفوع غير مكتملة.',
            'title.max' => 'عنوان الاختبار يجب ألا يتجاوز 255 حرفاً.',
            'due_date.date' => 'تاريخ تسليم النموذج الورقي غير صحيح.',
            'max_score.min' => 'الدرجة النهائية للاختبار يجب أن تكون 1 على الأقل.',
            'max_score.max' => 'الدرجة النهائية للاختبار يجب ألا تتجاوز 1000.',
        ];
    }

    private function unitHasPaperExam(): bool
    {
        return Worksheet::where('curriculum_unit_id', $this->route('unit'))
            ->where('type', Worksheet::TYPE_PAPER_EXAM)
            ->exists();
    }
}
