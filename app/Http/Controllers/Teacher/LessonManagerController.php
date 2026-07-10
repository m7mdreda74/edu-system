<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\CourseLesson;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonManagerController extends Controller
{
    public function index(int $courseId): Response
    {
        $course = $this->ownerCourseOrFail($courseId);

        return Inertia::render('Teacher/Lessons', [
            'course'  => $course->only('id', 'title', 'slug'),
            'lessons' => $course->lessons()->get(),
        ]);
    }

    public function store(Request $request, int $courseId): RedirectResponse
    {
        $course    = $this->ownerCourseOrFail($courseId);
        $validated = $request->validate([
            'title'            => ['required', 'string', 'min:3', 'max:200'],
            'video_url'        => ['required', 'url'],
            'duration_seconds' => ['required', 'integer', 'min:1'],
            'is_free_preview'  => ['boolean'],
            'description'      => ['nullable', 'string', 'max:1000'],
        ]);

        // Order: append to end
        $maxOrder = $course->lessons()->max('order') ?? 0;
        $validated['course_id'] = $course->id;
        $validated['order']     = $maxOrder + 1;

        CourseLesson::create($validated);
        // CourseLessonObserver automatically updates course.total_lessons

        return back()->with('success', 'تمت إضافة الدرس بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $lesson = CourseLesson::findOrFail($id);

        // Ownership check via course
        abort_unless(
            $lesson->course->teacher_id === auth()->id(),
            403,
            'غير مصرح.'
        );

        $lesson->delete();
        // CourseLessonObserver automatically updates totals on soft delete

        return back()->with('success', 'تم حذف الدرس.');
    }

    private function ownerCourseOrFail(int $id): Course
    {
        return Course::where('id', $id)
            ->where('teacher_id', auth()->id())
            ->firstOrFail();
    }
}
