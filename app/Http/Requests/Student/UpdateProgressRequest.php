<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'watched_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        ];
    }

    public function messages(): array
    {
        return [
            'watched_seconds.required' => 'حقل وقت المشاهدة مطلوب.',
            'watched_seconds.integer'  => 'وقت المشاهدة يجب أن يكون رقمًا صحيحًا.',
            'watched_seconds.max'      => 'قيمة وقت المشاهدة غير منطقية.',
        ];
    }
}
