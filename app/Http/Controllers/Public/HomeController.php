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
        $user = auth()->user();
        
        $cacheKey = ($user && $user->hasRole('student') && $user->grade_level)
            ? "courses.featured.{$user->grade_level}"
            : 'courses.featured.guest';

        $featuredCourses = Cache::remember($cacheKey, 1800, function () {
            return $this->courseRepository->getFeatured(8);
        });

        $subjectsCacheKey = ($user && $user->hasRole('student') && $user->grade_level)
            ? "subjects.active.{$user->grade_level}"
            : 'subjects.active.guest';

        $subjects = Cache::remember($subjectsCacheKey, 3600, function () use ($user) {
            $subjectsQuery = Subject::where('is_active', true);
            
            if ($user && $user->hasRole('student') && $user->grade_level) {
                $subjectsQuery->where(function ($q) use ($user) {
                    $q->where('grade_level', $user->grade_level)
                      ->orWhere('grade_level', 'all')
                      ->orWhereNull('grade_level');
                });
            }

            return $subjectsQuery->select('id', 'name', 'name_en', 'icon', 'grade_level')->get();
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
