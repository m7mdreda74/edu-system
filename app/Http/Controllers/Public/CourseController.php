<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Course\Contracts\CourseRepositoryInterface;
use App\Domain\Course\Models\Subject;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function __construct(
        private readonly CourseRepositoryInterface $courseRepository,
    ) {}

    public function index(Request $request): Response
    {
        // Validate query params (even GET requests need validation)
        $filters = $request->validate([
            'subject_id'  => ['nullable', 'integer', 'exists:subjects,id'],
            'grade_level' => ['nullable', 'string', 'in:grade_10,grade_11,grade_12,all'],
            'level'       => ['nullable', 'string', 'in:beginner,intermediate,advanced'],
            'search'      => ['nullable', 'string', 'max:100'],
            'sort'        => ['nullable', 'string', 'in:latest,popular,price_asc,price_desc'],
        ]);

        $courses  = $this->courseRepository->getPublished($filters, perPage: 12);
        $subjects = Subject::where('is_active', true)
            ->select('id', 'name', 'name_en', 'icon')
            ->get();

        return Inertia::render('Public/Courses', [
            'courses'  => $courses,
            'subjects' => $subjects,
            'filters'  => $filters,
        ]);
    }

    public function show(string $slug): Response
    {
        // Guard: 404 if course not found or unpublished
        $course = $this->courseRepository->findBySlug($slug);

        /** @var User|null $user */
        $user       = Auth::user();
        $isEnrolled = $user?->isEnrolledIn($course) ?? false;

        return Inertia::render('Public/CourseShow', [
            'course'     => $course,
            'isEnrolled' => $isEnrolled,
        ]);
    }

    public function autocomplete(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = $request->query('q', '');
        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $searchTerm = '%' . $search . '%';
        $courses = \App\Domain\Course\Models\Course::where('is_published', true)
            ->where(function($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                  ->orWhereHas('teacher', function ($q2) use ($searchTerm) {
                      $q2->where('name', 'like', $searchTerm);
                  });
            })
            ->with(['teacher:id,name'])
            ->limit(5)
            ->get(['id', 'title', 'slug', 'price', 'discount_price', 'teacher_id']);

        return response()->json($courses);
    }
}
