<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Validation\ValidationException;

class ParentStudentLinkService
{
    /** Link a student to an existing, active parent account identified by phone. */
    public function linkExistingParent(User $student, string $parentPhone): ParentStudentLink
    {
        $parent = User::query()
            ->where('phone', trim($parentPhone))
            ->where('is_active', true)
            ->first();

        if (! $parent || ! $parent->hasRole('parent')) {
            throw ValidationException::withMessages([
                'parent_phone' => 'لا يوجد حساب ولي أمر فعّال بهذا الرقم. يجب أن ينشئ ولي الأمر حسابه أولًا.',
            ]);
        }

        return ParentStudentLink::firstOrCreate(
            [
                'parent_user_id' => $parent->id,
                'student_user_id' => $student->id,
            ],
            [
                'relationship' => 'guardian',
                'verified_at' => now(),
            ],
        );
    }
}
