<?php

declare(strict_types=1);

namespace App\Infrastructure\Observers;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\CourseLesson;

/**
 * Keeps course.total_lessons in sync when lessons are added/deleted.
 * Ensures data consistency without manual updates in controllers.
 */
class CourseLessonObserver
{
    public function created(CourseLesson $lesson): void
    {
        $this->syncCourseTotals($lesson);
    }

    public function deleted(CourseLesson $lesson): void
    {
        $this->syncCourseTotals($lesson);
    }

    public function restored(CourseLesson $lesson): void
    {
        $this->syncCourseTotals($lesson);
    }

    private function syncCourseTotals(CourseLesson $lesson): void
    {
        $course = $lesson->course;

        if (! $course) {
            return;
        }

        $totalLessons   = $course->lessons()->count();
        $totalDuration  = (int) $course->lessons()->sum('duration_seconds');

        $course->updateQuietly([
            'total_lessons'  => $totalLessons,
            'total_duration' => $totalDuration,
        ]);
    }
}
