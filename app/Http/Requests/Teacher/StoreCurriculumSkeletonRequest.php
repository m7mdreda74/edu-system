<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * "هذا الفصل فيه 6 وحدات، كل وحدة 5 دروس" — the shell generator.
 *
 * The ceilings are deliberately low: a term that needs more than twenty units
 * is a data-entry mistake, and the generator writes rows in bulk.
 */
class StoreCurriculumSkeletonRequest extends FormRequest
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
            'academic_term_id' => ['required', 'integer', 'min:1', 'exists:academic_terms,id'],
            'units_count'      => ['required', 'integer', 'min:1', 'max:20'],
            'lessons_per_unit' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'academic_term_id.required' => 'اختر الفصل الدراسي أولاً.',
            'academic_term_id.exists'   => 'الفصل الدراسي المختار غير موجود.',
            'units_count.required'      => 'حدد عدد الوحدات.',
            'units_count.min'           => 'عدد الوحدات يجب أن يكون وحدة واحدة على الأقل.',
            'units_count.max'           => 'عدد الوحدات يجب ألا يتجاوز 20 وحدة.',
            'lessons_per_unit.required' => 'حدد عدد الدروس في كل وحدة.',
            'lessons_per_unit.min'      => 'عدد الدروس يجب أن يكون درساً واحداً على الأقل.',
            'lessons_per_unit.max'      => 'عدد الدروس في الوحدة يجب ألا يتجاوز 20 درساً.',
        ];
    }
}
