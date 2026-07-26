<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The front door. A visitor picks their grade, which opens the subjects, which
 * opens the teachers who teach them.
 *
 * Controller: 5 responsibilities only
 *   1. Validate (no input here)
 *   2. Authorize (public — no auth needed)
 *   3. Call Repository/Service
 *   4. Return Response
 *   5. Handle Exceptions (via Laravel's global handler)
 */
class HomeController extends Controller
{
    public function index(): Response
    {
        // Only grades a student can actually do something with — a grade with
        // no assigned teacher is a dead end.
        $grades = Cache::remember('home.grades', 1800, function () {
            return GradeLevel::where('is_active', true)
                ->whereIn('id', TeachingAssignment::where('is_active', true)->select('grade_level_id'))
                ->orderBy('id')
                ->get(['id', 'key', 'name', 'name_en', 'stage'])
                ->map(fn (GradeLevel $grade) => [
                    'id'             => $grade->id,
                    'key'            => $grade->key,
                    'name'           => $grade->name,
                    'name_en'        => $grade->name_en,
                    'stage'          => $grade->stage,
                    'subjects_count' => TeachingAssignment::where('grade_level_id', $grade->id)
                        ->where('is_active', true)
                        ->distinct('subject_id')
                        ->count('subject_id'),
                ])
                ->values();
        });

        $featuredTeachers = Cache::remember('home.featured_teachers', 900, function () {
            return User::role('teacher')
                ->where('is_active', true)
                ->orderByDesc('is_featured')
                ->take(8)
                ->get(['id', 'name', 'bio', 'headline', 'avatar', 'intro_video_url', 'intro_video_thumbnail', 'years_experience'])
                ->map(fn (User $teacher) => [
                    'id'                    => $teacher->id,
                    'name'                  => $teacher->name,
                    'headline'              => $teacher->headline,
                    'bio'                   => $teacher->bio,
                    'avatar'                => $teacher->avatar,
                    'intro_video_url'       => $teacher->intro_video_url,
                    'intro_video_thumbnail' => $teacher->intro_video_thumbnail,
                    'years_experience'      => $teacher->years_experience,
                    'rating'                => $teacher->averageRating(),
                    'subjects'              => $teacher->teachingAssignments()
                        ->where('is_active', true)
                        ->with('subject:id,name')
                        ->get()
                        ->pluck('subject.name')
                        ->filter()
                        ->unique()
                        ->values(),
                ])
                ->values();
        });

        return Inertia::render('Public/Home', [
            'grades'           => $grades,
            'featuredTeachers' => $featuredTeachers,
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
