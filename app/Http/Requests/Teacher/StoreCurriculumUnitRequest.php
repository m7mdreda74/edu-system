<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreCurriculumUnitRequest extends FormRequest
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
            'academic_term_id' => ['required', 'integer', 'exists:academic_terms,id'],
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'is_published'     => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'academic_term_id.required' => 'اختر الفصل الدراسي أولاً.',
            'academic_term_id.exists'   => 'الفصل الدراسي المختار غير موجود.',
            'title.required'            => 'عنوان الوحدة مطلوب.',
            'title.max'                 => 'عنوان الوحدة يجب ألا يتجاوز 255 حرفاً.',
            'description.max'           => 'وصف الوحدة يجب ألا يتجاوز 2000 حرف.',
        ];
    }
}
