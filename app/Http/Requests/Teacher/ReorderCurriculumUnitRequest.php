<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ReorderCurriculumUnitRequest extends FormRequest
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
            'direction' => ['required', 'string', 'in:up,down'],
        ];
    }

    public function messages(): array
    {
        return [
            'direction.required' => 'حدد اتجاه النقل.',
            'direction.in'       => 'اتجاه النقل غير صحيح.',
        ];
    }
}
