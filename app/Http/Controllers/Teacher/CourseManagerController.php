<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Course\Contracts\CourseRepositoryInterface;
use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\Subject;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CourseManagerController extends Controller
{
    public function __construct(
        private readonly CourseRepositoryInterface $courseRepository,
    ) {}

    public function index(): Response
    {
        $courses = auth()->user()
            ->coursesAsTeacher()
            ->with('subject:id,name')
            ->withCount('enrollments')
            ->latest()
            ->get();

        return Inertia::render('Teacher/Courses', ['courses' => $courses]);
    }

    public function create(): Response
    {
        return Inertia::render('Teacher/CourseForm', [
            'subjects' => Subject::where('is_active', true)->select('id', 'name')->get(),
            'course'   => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCourse($request);
        $validated['teacher_id'] = auth()->id();
        $validated['slug']       = $this->uniqueSlug($validated['title']);
        $validated['is_published'] = false; // Draft by default

        $course = $this->courseRepository->create($validated);

        return redirect()
            ->route('teacher.lessons', ['id' => $course->id])
            ->with('success', 'تم إنشاء الكورس! أضف الدروس الآن.');
    }

    public function edit(int $id): Response
    {
        $course = $this->ownerCourseOrFail($id);

        return Inertia::render('Teacher/CourseForm', [
            'subjects' => Subject::where('is_active', true)->select('id', 'name')->get(),
            'course'   => $course,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $course    = $this->ownerCourseOrFail($id);
        $validated = $this->validateCourse($request);

        $this->courseRepository->update($course, $validated);

        return back()->with('success', 'تم تحديث الكورس بنجاح.');
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function ownerCourseOrFail(int $id): Course
    {
        return Course::where('id', $id)
            ->where('teacher_id', auth()->id()) // Ownership check
            ->firstOrFail();
    }

    private function validateCourse(Request $request): array
    {
        return $request->validate([
            'title'          => ['required', 'string', 'min:5', 'max:200'],
            'description'    => ['required', 'string', 'min:20'],
            'subject_id'     => ['required', 'integer', 'exists:subjects,id'],
            'price'          => ['required', 'integer', 'min:0'],
            'discount_price' => ['nullable', 'integer', 'lt:price'],
            'grade_level'    => ['required', 'in:grade_10,grade_11,grade_12,all'],
            'level'          => ['required', 'in:beginner,intermediate,advanced'],
            'is_published'   => ['sometimes', 'boolean'],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i    = 1;

        while (Course::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
