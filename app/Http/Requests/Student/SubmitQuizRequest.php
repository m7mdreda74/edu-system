<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Validates quiz submission.
 * Answers must be an array keyed by question_id → array of option_ids.
 * Server always re-grades — client score is NEVER accepted.
 */
class SubmitQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'answers'                => ['present', 'array', 'max:200'],
            'answers.*'              => ['array', 'max:6'],
            'answers.*.*'            => ['integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'answers.present' => 'يجب تقديم إجابات الاختبار.',
            'answers.array'   => 'صيغة الإجابات غير صحيحة.',
        ];
    }
}
