<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Course\Models\Course;
use App\Domain\Enrollment\Contracts\EnrollmentServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Domain\Course\Models\Worksheet;
use App\Domain\Course\Models\WorksheetSubmission;
use App\Domain\Enrollment\Models\Enrollment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LearnController extends Controller
{
    public function __construct(
        private readonly EnrollmentServiceInterface $enrollmentService,
    ) {}

    public function show(string $slug): Response
    {
        $user   = Auth::user();
        $course = Course::with('teacher')->where('slug', $slug)->where('is_published', true)->firstOrFail();

        // Authorization check using Policies
        Gate::authorize('learn', $course);

        $enrollment = $this->enrollmentService->findEnrollment($user->id, $course->id);

        if (!$enrollment && $course->teacher_id === $user->id) {
            // Mock a temporary enrollment so the teacher can view/test their course learning page
            $enrollment = new Enrollment([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'progress_percent' => 100,
                'completed_at' => now(),
            ]);
        }

        if (! $enrollment) {
            abort(403, 'يجب التسجيل في الكورس أولاً.');
        }

        // Build progress map: lesson_id => { is_completed, watched_seconds }
        $progressMap = $enrollment->lessonProgress
            ->keyBy('lesson_id')
            ->map(fn($p) => [
                'is_completed'    => $p->is_completed,
                'watched_seconds' => $p->watched_seconds,
            ]);

        // Attach progress and lock status to lessons
        $previousLessonCompleted = true;
        $lessons = $enrollment->course->lessons->sortBy('order')->values()->map(function ($lesson) use ($progressMap, &$previousLessonCompleted) {
            $progress = $progressMap->get($lesson->id);
            $isCompleted = $progress['is_completed'] ?? false;
            
            // Exclude first lesson or free preview lessons from locking
            $isLocked = !$previousLessonCompleted && !$lesson->is_free_preview && $lesson->order > 1;
            
            $previousLessonCompleted = $isCompleted;

            return [
                'id'               => $lesson->id,
                'title'            => $lesson->title,
                'video_url'        => $lesson->video_url,
                'duration_seconds' => $lesson->duration_seconds,
                'order'            => $lesson->order,
                'is_free_preview'  => $lesson->is_free_preview,
                'is_completed'     => $isCompleted,
                'watched_seconds'  => $progress['watched_seconds'] ?? 0,
                'is_locked'        => $isLocked,
            ];
        });

        return Inertia::render('Student/Learn', [
            'course'      => [
                'id'           => $course->id,
                'title'        => $course->title,
                'slug'         => $course->slug,
                'total_lessons'=> $course->total_lessons,
                'teacher'      => [
                    'id'   => $course->teacher_id,
                    'name' => $course->teacher ? $course->teacher->name : null,
                ],
            ],
            'lessons'     => $lessons,
            'enrollment'  => [
                'id'               => $enrollment->id,
                'progress_percent' => $enrollment->progress_percent,
                'completed_at'     => $enrollment->completed_at,
            ],
            'worksheets'  => Worksheet::where('course_id', $course->id)
                ->with(['submissions' => fn($q) => $q->where('student_id', $user->id)])
                ->get(),
        ]);
    }

    /**
     * AJAX endpoint: Update lesson progress
     * Called by the video player every N seconds + on completion.
     */
    public function updateProgress(Request $request, string $slug, int $lessonId): JsonResponse
    {
        $validated = $request->validate([
            'watched_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        ]);

        $user   = Auth::user();
        $course = Course::where('slug', $slug)->firstOrFail();

        $enrollment = $this->enrollmentService->findEnrollment($user->id, $course->id);

        if (! $enrollment) {
            return response()->json(['error' => 'Not enrolled'], 403);
        }

        $this->enrollmentService->markLessonWatched(
            enrollment:     $enrollment,
            lessonId:       $lessonId,
            watchedSeconds: $validated['watched_seconds'],
        );

        // Return updated enrollment progress for UI sync
        $enrollment->refresh();

        // Re-build progress map and lessons list to return to client
        $progressMap = $enrollment->lessonProgress
            ->keyBy('lesson_id')
            ->map(fn($p) => [
                'is_completed'    => $p->is_completed,
                'watched_seconds' => $p->watched_seconds,
            ]);

        $previousLessonCompleted = true;
        $lessons = $enrollment->course->lessons->sortBy('order')->values()->map(function ($lesson) use ($progressMap, &$previousLessonCompleted) {
            $progress = $progressMap->get($lesson->id);
            $isCompleted = $progress['is_completed'] ?? false;
            $isLocked = !$previousLessonCompleted && !$lesson->is_free_preview && $lesson->order > 1;
            $previousLessonCompleted = $isCompleted;

            return [
                'id'               => $lesson->id,
                'title'            => $lesson->title,
                'video_url'        => $lesson->video_url,
                'duration_seconds' => $lesson->duration_seconds,
                'order'            => $lesson->order,
                'is_free_preview'  => $lesson->is_free_preview,
                'is_completed'     => $isCompleted,
                'watched_seconds'  => $progress['watched_seconds'] ?? 0,
                'is_locked'        => $isLocked,
            ];
        });

        return response()->json([
            'progress_percent' => $enrollment->progress_percent,
            'is_completed'     => $enrollment->isCompleted(),
            'lessons'          => $lessons,
        ]);
    }

    public function submitHomework(Request $request, string $slug, int $worksheetId): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'submitted_file' => ['required', 'file', 'mimes:pdf,docx,png,jpg,jpeg', 'max:10240'], // 10MB
        ]);

        $user   = Auth::user();
        $course = Course::where('slug', $slug)->firstOrFail();
        $worksheet = Worksheet::where('course_id', $course->id)->findOrFail($worksheetId);

        abort_if(! $worksheet->requires_submission, 403, 'هذا الشيت لا يتطلب تسليماً.');

        // File upload
        $path = $request->file('submitted_file')->store('submissions', 'public');

        WorksheetSubmission::updateOrCreate(
            [
                'worksheet_id' => $worksheet->id,
                'student_id'   => $user->id,
            ],
            [
                'submitted_file_path' => '/storage/' . $path,
                'submitted_at'        => now(),
                'score'               => null, // Reset if resubmitting
                'teacher_feedback'    => null,
                'graded_at'           => null,
            ]
        );

        return back()->with('success', 'تم تسليم الواجب بنجاح.');
    }
}
