<?php

declare(strict_types=1);

namespace App\Infrastructure\Observers;

use App\Domain\Course\Models\Course;

/**
 * Reacts to Course events.
 * Clears course cache when course is updated or published.
 */
class CourseObserver
{
    public function updated(Course $course): void
    {
        // Invalidate course cache when updated — Phase 3/4
        // Cache::forget("course:{$course->slug}");
        // Cache::forget('courses.featured');
    }

    public function deleted(Course $course): void
    {
        // Notify enrolled students — Phase 4
        // \App\Jobs\NotifyStudentsOfCourseRemoval::dispatch($course->id);
    }
}
