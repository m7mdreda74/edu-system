<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Course\Models\Course;
use App\Domain\User\Models\User;

/**
 * CoursePolicy — centralizes all course authorization logic.
 * Controllers call Gate/Policy instead of raw ownership checks.
 */
class CoursePolicy
{
    /**
     * Anyone can view a published course.
     * Admins and the course's teacher can always view.
     */
    public function view(?User $user, Course $course): bool
    {
        return $course->is_published
            || ($user?->isAdmin())
            || ($user?->id === $course->teacher_id);
    }

    /**
     * Only the owning teacher can update their course.
     */
    public function update(User $user, Course $course): bool
    {
        return $user->id === $course->teacher_id
            || $user->isAdmin();
    }

    /**
     * Only the owning teacher can delete.
     */
    public function delete(User $user, Course $course): bool
    {
        return $user->id === $course->teacher_id
            || $user->isAdmin();
    }

    /**
     * Only the owning teacher can manage lessons.
     */
    public function manageLesson(User $user, Course $course): bool
    {
        return $user->id === $course->teacher_id;
    }

    /**
     * Admin can toggle publish status.
     */
    public function publish(User $user, Course $course): bool
    {
        return $user->isAdmin();
    }
}
