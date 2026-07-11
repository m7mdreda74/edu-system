<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('teacher') ?? false;
    }

    public function rules(): array
    {
        return [
            'subject_id'     => ['required', 'integer', 'exists:subjects,id'],
            'title'          => ['required', 'string', 'min:5', 'max:200'],
            'description'    => ['required', 'string', 'min:20', 'max:5000'],
            'price'          => ['required', 'integer', 'min:0'],       // in halala
            'discount_price' => ['nullable', 'integer', 'min:0', 'lt:price'],
            'grade_level'    => ['required', 'exists:grade_levels,key'],
            'level'          => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'is_published'   => ['boolean'],
            'thumbnail'      => ['nullable', 'image', 'max:2048'],      // 2MB max
        ];
    }

    public function messages(): array
    {
        return [
            'title.min'           => 'عنوان الكورس يجب أن يكون 5 أحرف على الأقل.',
            'description.min'     => 'وصف الكورس يجب أن يكون 20 حرفًا على الأقل.',
            'discount_price.lt'   => 'سعر الخصم يجب أن يكون أقل من السعر الأصلي.',
            'subject_id.exists'   => 'المادة الدراسية غير موجودة.',
        ];
    }
}
