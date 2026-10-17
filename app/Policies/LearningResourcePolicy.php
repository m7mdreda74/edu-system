<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Learning\Models\LearningResource;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;

class LearningResourcePolicy
{
    public function view(User $user, LearningResource $resource): bool
    {
        if (! $resource->is_active || ! filled($resource->file_path)) {
            return false;
        }

        if ($user->isAdmin() || $resource->owner_teacher_id === $user->id) {
            return true;
        }

        if ($user->isTeacher()) {
            return $resource->isApprovedShared();
        }

        $assignmentIds = $resource->assignments()->select('teaching_assignments.id');

        if ($user->isStudent()) {
            return $resource->approval_status === LearningResource::APPROVAL_APPROVED
                && Subscription::active()
                    ->where('student_id', $user->id)
                    ->whereIn('teaching_assignment_id', $assignmentIds)
                    ->exists();
        }

        if ($user->isParent()) {
            $studentIds = ParentStudentLink::query()
                ->where('parent_user_id', $user->id)
                ->whereNotNull('verified_at')
                ->select('student_user_id');

            return $resource->approval_status === LearningResource::APPROVAL_APPROVED
                && Subscription::active()
                    ->whereIn('student_id', $studentIds)
                    ->whereIn('teaching_assignment_id', $assignmentIds)
                    ->exists();
        }

        return false;
    }

    public function update(User $user, LearningResource $resource): bool
    {
        return $user->isAdmin() || $resource->owner_teacher_id === $user->id;
    }

    public function delete(User $user, LearningResource $resource): bool
    {
        return $user->isAdmin() || $resource->owner_teacher_id === $user->id;
    }

    public function requestShare(User $user, LearningResource $resource): bool
    {
        return $resource->owner_teacher_id === $user->id
            && $resource->is_active
            && filled($resource->file_path);
    }

    public function moderate(User $user, LearningResource $resource): bool
    {
        return $user->isAdmin();
    }
}
