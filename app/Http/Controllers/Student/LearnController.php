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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LearnController extends Controller
{
    public function __construct(
        private readonly EnrollmentServiceInterface $enrollmentService,
    ) {}

    public function show(string $slug): Response
    {
        $user   = auth()->user();
        $course = Course::where('slug', $slug)->where('is_published', true)->firstOrFail();

        // Authorization: only enrolled students
        $enrollment = $this->enrollmentService->findEnrollment($user->id, $course->id);

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

        // Attach progress to lessons
        $lessons = $enrollment->course->lessons->map(function ($lesson) use ($progressMap) {
            $progress = $progressMap->get($lesson->id);
            return [
                'id'               => $lesson->id,
                'title'            => $lesson->title,
                'video_url'        => $lesson->video_url,
                'duration_seconds' => $lesson->duration_seconds,
                'order'            => $lesson->order,
                'is_free_preview'  => $lesson->is_free_preview,
                'is_completed'     => $progress['is_completed'] ?? false,
                'watched_seconds'  => $progress['watched_seconds'] ?? 0,
            ];
        });

        return Inertia::render('Student/Learn', [
            'course'      => [
                'id'           => $course->id,
                'title'        => $course->title,
                'slug'         => $course->slug,
                'total_lessons'=> $course->total_lessons,
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

        $user   = auth()->user();
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

        return response()->json([
            'progress_percent' => $enrollment->progress_percent,
            'is_completed'     => $enrollment->isCompleted(),
        ]);
    }

    public function submitHomework(Request $request, string $slug, int $worksheetId): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'submitted_file' => ['required', 'file', 'mimes:pdf,docx,png,jpg,jpeg', 'max:10240'], // 10MB
        ]);

        $user   = auth()->user();
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
