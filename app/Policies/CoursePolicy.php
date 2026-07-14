<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Course\Models\Course;
use App\Domain\User\Models\User;

class CoursePolicy
{
    /**
     * Determine if the user can learn the course.
     */
    public function learn(User $user, Course $course): bool
    {
        return $user->isEnrolledIn($course) || $course->teacher_id === $user->id;
    }

    /**
     * Determine if the user can join the live session of the course.
     */
    public function joinLive(User $user, Course $course): bool
    {
        return $user->isEnrolledIn($course) || $course->teacher_id === $user->id;
    }
}
