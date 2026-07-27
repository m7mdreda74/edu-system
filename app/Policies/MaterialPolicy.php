<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\User\Models\User;

/**
 * MaterialPolicy — controls access to a lesson.
 *
 * Core business rule: paid material needs a live subscription. Access is a
 * window in time now, so a lapsed subscription locks the content again.
 *
 * The syllabus hangs off the teaching assignment rather than off a timetable
 * slot, so the subscription is checked against the assignment. That is what
 * lets a private student — who has no group at all — reach the same lessons.
 */
class MaterialPolicy
{
    public function watch(User $user, GroupMaterial $material): bool
    {
        $material->loadMissing('unit.assignment');
        $assignment = $material->unit?->assignment;

        if (! $assignment) {
            return false;
        }

        // Admins, and the teacher who wrote the syllabus, always have access.
        if ($user->isAdmin() || $user->id === $assignment->teacher_id) {
            return true;
        }

        if ($material->is_free_preview) {
            return true;
        }

        return $user->hasActiveSubscriptionToAssignment($assignment->id);
    }
}
