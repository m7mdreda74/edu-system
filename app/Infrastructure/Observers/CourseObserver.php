<?php

declare(strict_types=1);

namespace App\Infrastructure\Observers;

use App\Domain\Course\Models\Course;
use Illuminate\Support\Facades\Cache;

/**
 * Reacts to Course events.
 * Clears course cache when course is updated or published.
 */
class CourseObserver
{
    public function updated(Course $course): void
    {
        // Invalidate course cache when updated
        Cache::forget("courses.featured.guest");
        if ($course->grade_level) {
            Cache::forget("courses.featured.{$course->grade_level}");
        }
    }
}
