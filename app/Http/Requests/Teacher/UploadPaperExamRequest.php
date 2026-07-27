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
        return [
            'file'                => [$this->unitHasPaperExam() ? 'nullable' : 'required', 'file', 'max:' . UploadBookletRequest::MAX_KILOBYTES],
            'title'               => ['nullable', 'string', 'max:255'],
            'due_date'            => ['nullable', 'date'],
            'max_score'           => ['nullable', 'integer', 'min:1', 'max:1000'],
            'requires_submission' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'اختر ملف النموذج الورقي أولاً.',
            'file.file'     => 'النموذج الورقي يجب أن يكون ملفاً.',
            'file.max'      => 'حجم النموذج الورقي يجب ألا يتجاوز 25 ميجابايت.',
            'title.max'     => 'عنوان الاختبار يجب ألا يتجاوز 255 حرفاً.',
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
