<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\CourseLesson;
use App\Domain\User\Models\User;

/**
 * LessonPolicy — controls access to lesson video content.
 * Core business rule: paid content requires active enrollment.
 */
class LessonPolicy
{
    /**
     * Can the user watch this lesson?
     *
     * Free-preview lessons are accessible to all authenticated users.
     * Paid content requires an active enrollment.
     */
    public function watch(User $user, CourseLesson $lesson): bool
    {
        // Admin and the course's teacher always have access
        if ($user->isAdmin() || $user->id === $lesson->course->teacher_id) {
            return true;
        }

        // Free preview is accessible to any authenticated user
        if ($lesson->is_free_preview) {
            return true;
        }

        // Must be enrolled to watch paid content
        return $user->isEnrolledIn($lesson->course);
    }
}
