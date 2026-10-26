<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * The settings the client named for an electronic quiz: "يحدد مدتها ومتاح من/إلى".
 *
 * Only the teacher role is checked here — which quiz belongs to whom is the
 * controller's business, since it is the one that resolved the row.
 */
class QuizSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('teacher') ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'title'                 => ['required', 'string', 'min:3', 'max:255'],
            'time_limit_minutes'    => ['nullable', 'integer', 'min:1', 'max:600'],
            'available_from'        => ['nullable', 'date'],
            'available_until'       => ['nullable', 'date'],
            'allow_late_submission' => ['nullable', 'boolean'],
            'max_attempts'          => ['nullable', 'integer', 'min:1', 'max:20'],
            'passing_score'         => ['required', 'integer', 'min:0', 'max:100'],
            'is_active'             => ['required', 'boolean'],
            'lesson_id'             => ['nullable', 'integer', 'min:1', 'exists:group_materials,id'],
        ];

        // `after:` falls back to reading its argument as a literal date when the
        // other field is absent, so the ordering rule is only worth applying
        // once there is a start to compare against.
        if ($this->filled('available_from')) {
            $rules['available_until'][] = 'after:available_from';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'title.required'             => 'عنوان الاختبار مطلوب.',
            'title.min'                  => 'عنوان الاختبار قصير جداً.',
            'title.max'                  => 'عنوان الاختبار طويل جداً.',
            'time_limit_minutes.integer' => 'مدة الاختبار تُكتب بالدقائق.',
            'time_limit_minutes.min'     => 'مدة الاختبار يجب ألا تقل عن دقيقة واحدة.',
            'time_limit_minutes.max'     => 'مدة الاختبار يجب ألا تتجاوز 600 دقيقة.',
            'available_from.date'        => 'تاريخ بداية الإتاحة غير صحيح.',
            'available_until.date'       => 'تاريخ نهاية الإتاحة غير صحيح.',
            'available_until.after'      => 'نهاية الإتاحة يجب أن تكون بعد بدايتها.',
            'max_attempts.integer'       => 'عدد المحاولات يجب أن يكون رقماً صحيحاً.',
            'max_attempts.min'           => 'عدد المحاولات يجب أن يكون 1 على الأقل.',
            'max_attempts.max'           => 'عدد المحاولات يجب ألا يتجاوز 20.',
            'passing_score.required'     => 'درجة النجاح مطلوبة.',
            'passing_score.integer'      => 'درجة النجاح تُكتب كنسبة مئوية.',
            'passing_score.min'          => 'درجة النجاح يجب أن تكون بين 0 و100.',
            'passing_score.max'          => 'درجة النجاح يجب أن تكون بين 0 و100.',
            'is_active.required'         => 'حدد ما إذا كان الاختبار مفعّلاً.',
            'lesson_id.exists'           => 'الدرس المحدد غير موجود.',
        ];
    }

    /**
     * The quiz settings.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $data = Arr::only($this->validated(), [
            'title',
            'time_limit_minutes',
            'available_from',
            'available_until',
            'allow_late_submission',
            'max_attempts',
            'passing_score',
            'is_active',
        ]);

        if ($this->has('allow_late_submission')) {
            $data['allow_late_submission'] = $this->boolean('allow_late_submission');
        }

        if ($this->has('lesson_id')) {
            $data['lesson_id'] = $this->filled('lesson_id') ? (int) $this->input('lesson_id') : null;
        }

        return $data;
    }
}
