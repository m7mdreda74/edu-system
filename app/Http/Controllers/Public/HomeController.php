<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Course\Contracts\CourseRepositoryInterface;
use App\Domain\Course\Models\Subject;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller: 5 responsibilities only
 *   1. Validate (no input here)
 *   2. Authorize (public — no auth needed)
 *   3. Call Repository/Service
 *   4. Return Response
 *   5. Handle Exceptions (via Laravel's global handler)
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly CourseRepositoryInterface $courseRepository,
    ) {}

    public function index(): Response
    {
        // Cache homepage data for 30 minutes — heavy query
        $featuredCourses = Cache::remember('courses.featured', 1800, function () {
            return $this->courseRepository->getFeatured(8);
        });

        $subjects = Cache::remember('subjects.active', 3600, function () {
            return Subject::where('is_active', true)
                ->select('id', 'name', 'name_en', 'icon', 'grade_level')
                ->get();
        });

        return Inertia::render('Public/Home', [
            'featuredCourses' => $featuredCourses,
            'subjects'        => $subjects,
            'teachers'        => \App\Domain\User\Models\User::role('teacher')->select('id', 'name', 'bio', 'avatar')->take(6)->get(),
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('Public/About');
    }

    public function contact(): Response
    {
        return Inertia::render('Public/Contact');
    }

    public function ourApps(): Response
    {
        return Inertia::render('Public/OurApps');
    }

    public function studentsResults(): Response
    {
        return Inertia::render('Public/StudentsResults');
    }
}
