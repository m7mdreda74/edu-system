<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\User\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * A question arrives with its answers in one payload — "يضيف الأسئلة وإجاباتها
 * كاختيار من متعدد" — because a question without an answer key cannot be graded
 * and must never reach the database in that state.
 */
class QuizQuestionRequest extends FormRequest
{
    /** Two is the smallest set that asks the student anything. */
    private const MIN_OPTIONS = 2;

    private const MAX_OPTIONS = 6;

    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('teacher') ?? false;
    }

    public function rules(): array
    {
        return [
            'question_text'         => ['required', 'string', 'min:3', 'max:1000'],
            'type'                  => ['required', 'string', 'in:single,multiple'],
            'points'                => ['nullable', 'integer', 'min:1', 'max:100'],
            'order'                 => ['nullable', 'integer', 'min:1', 'max:999'],
            'options'               => ['required', 'array', 'min:' . self::MIN_OPTIONS, 'max:' . self::MAX_OPTIONS],
            'options.*.option_text' => ['required', 'string', 'max:500'],
            'options.*.is_correct'  => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'question_text.required'         => 'نص السؤال مطلوب.',
            'question_text.min'              => 'نص السؤال قصير جداً.',
            'question_text.max'              => 'نص السؤال طويل جداً.',
            'type.required'                  => 'حدد نوع السؤال.',
            'type.in'                        => 'نوع السؤال غير معروف.',
            'points.integer'                 => 'نقاط السؤال يجب أن تكون رقمًا صحيحًا.',
            'points.min'                     => 'السؤال يجب أن يساوي نقطة واحدة على الأقل.',
            'points.max'                     => 'لا يمكن أن تزيد نقاط السؤال عن 100.',
            'options.required'               => 'أضف إجابات السؤال.',
            'options.array'                  => 'صيغة الإجابات غير صحيحة.',
            'options.min'                    => 'يجب أن يحتوي السؤال على إجابتين على الأقل.',
            'options.max'                    => 'لا يمكن أن يزيد عدد الإجابات عن ست إجابات.',
            'options.*.option_text.required' => 'نص الإجابة مطلوب.',
            'options.*.option_text.max'      => 'نص الإجابة طويل جداً.',
            'options.*.is_correct.required'  => 'حدد ما إذا كانت الإجابة صحيحة.',
            'options.*.is_correct.boolean'   => 'حدد ما إذا كانت الإجابة صحيحة.',
        ];
    }

    /**
     * The answer key: a rule the message bag cannot express on its own, because
     * it reads the options as a set rather than one at a time.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // The shape is already wrong; a second complaint about it would
                // only bury the first.
                if ($validator->errors()->hasAny(['type', 'options', 'options.*.is_correct'])) {
                    return;
                }

                $correct = count($this->correctIndexes());

                if ($correct === 0) {
                    $validator->errors()->add('options', 'يجب تحديد إجابة صحيحة واحدة على الأقل.');

                    return;
                }

                if ($this->input('type') === 'single' && $correct > 1) {
                    $validator->errors()->add('options', 'سؤال الاختيار الواحد يقبل إجابة صحيحة واحدة فقط.');
                }
            },
        ];
    }

    /**
     * Indexes of the options ticked as correct.
     *
     * @return array<int, array-key>
     */
    public function correctIndexes(): array
    {
        $options = $this->input('options');

        if (! is_array($options)) {
            return [];
        }

        return array_keys(array_filter(
            $options,
            static fn ($option): bool => is_array($option) && (bool) ($option['is_correct'] ?? false),
        ));
    }
}
