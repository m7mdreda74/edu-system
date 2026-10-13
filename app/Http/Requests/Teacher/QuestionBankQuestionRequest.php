<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use Illuminate\Validation\Rule;

class QuestionBankQuestionRequest extends QuizQuestionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'curriculum_unit_id' => ['nullable', 'integer', 'exists:curriculum_units,id'],
            'lesson_id' => ['nullable', 'integer', 'exists:group_materials,id'],
            'topic' => ['nullable', 'string', 'max:160'],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
            'visibility' => ['required', Rule::in(['private', 'shared'])],
        ]);
    }

    /** @return array<string, mixed> */
    public function bankData(): array
    {
        $data = $this->validated();
        $data['tags'] = $data['tags'] ?? [];

        return $data;
    }
}
