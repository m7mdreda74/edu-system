<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Academic\Models\AcademicTerm;
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
        $grades = Cache::remember('home.grades', 1800, function () {
            $subjectCounts = GradeLevel::where('is_active', true)
                ->withCount('subjects')
                ->pluck('subjects_count', 'id');

            $teacherCounts = TeachingAssignment::where('is_active', true)
                ->get(['grade_level_id', 'teacher_id'])
                ->groupBy('grade_level_id')
                ->map(fn ($assignments) => $assignments->pluck('teacher_id')->unique()->count());

            return GradeLevel::where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'key', 'name', 'name_en', 'stage', 'track'])
                ->map(fn (GradeLevel $grade) => [
                    'id' => $grade->id,
                    'key' => $grade->key,
                    'name' => $grade->name,
                    'name_en' => $grade->name_en,
                    'stage' => $grade->stage,
                    'stage_label' => $grade->stageLabel(),
                    'track' => $grade->track,
                    'track_label' => $grade->trackLabel(),
                    'subjects_count' => (int) ($subjectCounts[$grade->id] ?? 0),
                    'teachers_count' => (int) ($teacherCounts->get($grade->id, 0)),
                ])
                ->values();
        });

        $featuredTeachers = Cache::remember('home.featured_teachers', 900, function () {
            return User::role('teacher')
                ->where('is_active', true)
                ->orderByDesc('is_featured')
                ->take(8)
                ->withAvg([
                    'reviewsReceived as approved_rating' => fn ($query) => $query->where('is_approved', true),
                ], 'rating')
                ->with([
                    'teachingAssignments' => fn ($query) => $query
                        ->where('is_active', true)
                        ->with('subject:id,name'),
                ])
                ->get(['id', 'name', 'bio', 'headline', 'avatar', 'intro_video_url', 'intro_video_thumbnail', 'years_experience'])
                ->map(fn (User $teacher) => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'headline' => $teacher->headline,
                    'bio' => $teacher->bio,
                    'avatar' => $teacher->avatar,
                    'intro_video_url' => $teacher->intro_video_url,
                    'intro_video_thumbnail' => $teacher->intro_video_thumbnail,
                    'years_experience' => $teacher->years_experience,
                    'rating' => round((float) ($teacher->approved_rating ?? 0), 1),
                    'subjects' => $teacher->teachingAssignments
                        ->pluck('subject.name')
                        ->filter()
                        ->unique()
                        ->values(),
                ])
                ->values();
        });

        $term = AcademicTerm::currentOrNext();

        return Inertia::render('Public/Home', [
            'term' => $term ? [
                'name' => $term->fullName(),
                'starts_on' => $term->starts_on?->toDateString(),
                'ends_on' => $term->ends_on?->toDateString(),
                'is_current' => $term->isCurrent(),
                'provisional' => $term->is_provisional,
            ] : null,
            'grades' => $grades,
            'featuredTeachers' => $featuredTeachers,
            'stats' => [
                'registered_students' => User::role('student')
                    ->where('is_active', true)
                    ->count(),
                'available_courses' => TeachingAssignment::query()
                    ->where('is_active', true)
                    ->whereHas('teacher', fn ($query) => $query->where('is_active', true))
                    ->count(),
                'active_teachers' => User::role('teacher')
                    ->where('is_active', true)
                    ->whereHas('teachingAssignments', fn ($query) => $query->where('is_active', true))
                    ->count(),
            ],
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
