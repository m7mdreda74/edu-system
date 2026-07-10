<?php

declare(strict_types=1);

namespace App\Application\Enrollment\Services;

use App\Domain\Course\Models\Course;
use App\Domain\Enrollment\Contracts\EnrollmentServiceInterface;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\LessonProgress;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use App\Notifications\CourseEnrolledNotification;
use App\Domain\Communication\Notifications\StudentEnrolledNotification;

/**
 * EnrollmentService — Application Layer
 * Orchestrates enrollment business logic.
 * No Eloquent queries here directly; uses models/repositories.
 */
class EnrollmentService implements EnrollmentServiceInterface
{
    /**
     * Enroll a student in a free course.
     * DB transaction ensures atomicity.
     *
     * @throws LogicException if course is paid or already enrolled
     */
    public function enrollFree(User $user, Course $course): Enrollment
    {
        if (! $course->isFree()) {
            throw new LogicException("Course [{$course->id}] is not free. Use payment flow.");
        }

        // DB-level unique constraint prevents double enrollment,
        // but we check early to give a better error message
        $existing = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existing) {
            return $existing; // Idempotent — return existing enrollment
        }

        return DB::transaction(function () use ($user, $course) {
            $enrollment = Enrollment::create([
                'user_id'          => $user->id,
                'course_id'        => $course->id,
                'progress_percent' => 0,
                'enrolled_at'      => now(),
            ]);

            // Eager load teacher
            $course->load('teacher');

            // Notify Student
            $user->notify(new CourseEnrolledNotification($course));

            // Notify Teacher
            if ($course->teacher) {
                $course->teacher->notify(new StudentEnrolledNotification($course, $user));
            }

            return $enrollment;
        });
    }

    /**
     * Mark a lesson as watched/completed and update progress.
     * Progress is ALWAYS computed server-side — never trust client.
     */
    public function markLessonWatched(
        Enrollment $enrollment,
        int $lessonId,
        int $watchedSeconds
    ): void {
        // Verify lesson belongs to this enrollment's course (Authorization guard)
        $lesson = $enrollment->course->lessons()->findOrFail($lessonId);

        DB::transaction(function () use ($enrollment, $lesson, $watchedSeconds) {
            $progress = LessonProgress::updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'lesson_id'     => $lesson->id,
                ],
                [
                    'watched_seconds' => max($watchedSeconds, 0), // never negative
                    'is_completed'    => $watchedSeconds >= ($lesson->duration_seconds * 0.8),
                    // 80% watched = completed (prevents gaming by jumping to end)
                ]
            );

            // Recompute overall progress after each lesson update
            $enrollment->recalculateProgress();
        });
    }

    /**
     * Get all enrollments with course data for a student.
     * N+1 safe — eager loaded.
     */
    public function getStudentEnrollments(int $userId): Collection
    {
        return Enrollment::with([
            'course:id,title,slug,thumbnail,total_lessons,total_duration,teacher_id',
            'course.teacher:id,name,avatar',
            'course.subject:id,name,icon',
        ])
        ->where('user_id', $userId)
        ->orderBy('created_at', 'desc')
        ->get();
    }

    public function findEnrollment(int $userId, int $courseId): ?Enrollment
    {
        return Enrollment::with([
            'course.lessons:id,course_id,title,duration_seconds,order,is_free_preview',
            'lessonProgress',
        ])
        ->where('user_id', $userId)
        ->where('course_id', $courseId)
        ->first();
    }
}
