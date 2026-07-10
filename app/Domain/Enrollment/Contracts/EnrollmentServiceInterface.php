<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Contracts;

use App\Domain\Course\Models\Course;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\User\Models\User;

interface EnrollmentServiceInterface
{
    public function enrollFree(User $user, Course $course): Enrollment;

    public function markLessonWatched(
        Enrollment $enrollment,
        int $lessonId,
        int $watchedSeconds
    ): void;

    public function getStudentEnrollments(int $userId): \Illuminate\Database\Eloquent\Collection;

    public function findEnrollment(int $userId, int $courseId): ?Enrollment;
}
