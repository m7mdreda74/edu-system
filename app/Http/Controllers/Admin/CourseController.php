<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Course\Models\Course;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $courses = Course::with(['teacher:id,name', 'subject:id,name'])
            ->withCount('enrollments')
            ->when($request->search, fn ($q) =>
                $q->where('title', 'like', '%' . $request->search . '%')
            )
            ->when($request->teacher_id, fn ($q) =>
                $q->where('teacher_id', $request->teacher_id)
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Courses', [
            'courses' => $courses,
            'filters' => $request->only('search', 'teacher_id'),
        ]);
    }

    public function togglePublish(int $id): RedirectResponse
    {
        $course = Course::findOrFail($id);
        $course->update(['is_published' => ! $course->is_published]);

        // Clear homepage cached courses
        Cache::forget('courses.featured');

        $status = $course->is_published ? 'نشر' : 'إخفاء';

        return back()->with('success', "تم {$status} كورس: {$course->title}");
    }
}
