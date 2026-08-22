<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Every rule is `sometimes`: the builder edits a unit field at a time — the
 * title inline in the accordion header, the publish switch on its own — so a
 * request carries only what changed.
 */
class UpdateCurriculumUnitRequest extends FormRequest
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
            'title'        => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'description'  => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'  => 'عنوان الوحدة مطلوب.',
            'title.max'       => 'عنوان الوحدة يجب ألا يتجاوز 255 حرفاً.',
            'description.max' => 'وصف الوحدة يجب ألا يتجاوز 2000 حرف.',
        ];
    }
}
