<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Validation\ValidationException;

class ParentStudentLinkService
{
    /** Link an active student to the parent who initiated the request. */
    public function linkExistingStudent(
        User $parent,
        string $studentPhone,
        string $relationship = 'guardian',
    ): ParentStudentLink {
        $student = User::query()
            ->where('phone', trim($studentPhone))
            ->where('is_active', true)
            ->first();

        if (! $student || ! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student_phone' => 'لا يوجد حساب طالب فعّال بهذا الرقم.',
            ]);
        }

        if ($student->is($parent)) {
            throw ValidationException::withMessages([
                'student_phone' => 'لا يمكن ربط حساب ولي الأمر بحسابه نفسه.',
            ]);
        }

        return ParentStudentLink::firstOrCreate(
            [
                'parent_user_id' => $parent->id,
                'student_user_id' => $student->id,
            ],
            [
                'relationship' => $relationship,
                'verified_at' => now(),
            ],
        );
    }

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
