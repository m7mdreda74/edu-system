<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step two of the browse flow: a grade opens the subjects on its curriculum.
 *
 * The whole curriculum is listed, not only the parts that have a teacher — a
 * visitor should see that their grade is covered. Subjects nobody teaches yet
 * are shown as coming soon rather than linking to an empty page.
 */
class GradeLevelBrowseController extends Controller
{
    public function show(string $key): Response
    {
        $grade = GradeLevel::where('key', $key)
            ->where('is_active', true)
            ->firstOrFail();

        $teacherCounts = TeachingAssignment::where('grade_level_id', $grade->id)
            ->where('is_active', true)
            ->whereHas('teacher', fn ($q) => $q->where('is_active', true))
            ->get(['subject_id', 'teacher_id'])
            ->groupBy('subject_id')
            ->map(fn ($assignments) => $assignments->pluck('teacher_id')->unique()->count());

        // The curriculum, plus anything actually being taught at this grade —
        // an admin can assign a teacher to a subject the plan does not list,
        // and that must never vanish from the browse flow.
        $subjectIds = $grade->subjects()->pluck('subjects.id')
            ->merge($teacherCounts->keys())
            ->unique();

        $subjects = Subject::whereIn('id', $subjectIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'name_en', 'icon'])
            ->map(fn (Subject $subject) => [
                'id'             => $subject->id,
                'name'           => $subject->name,
                'name_en'        => $subject->name_en,
                'icon'           => $subject->icon,
                'teachers_count' => $teacherCounts->get($subject->id, 0),
            ])
            // Subjects with teachers first — the visitor can act on those.
            ->sortByDesc('teachers_count')
            ->values();

        return Inertia::render('Public/GradeSubjects', [
            'grade'    => [
                'id'          => $grade->id,
                'key'         => $grade->key,
                'name'        => $grade->name,
                'name_en'     => $grade->name_en,
                'stage'       => $grade->stage,
                'stage_label' => $grade->stageLabel(),
                'track'       => $grade->track,
                'track_label' => $grade->trackLabel(),
            ],
            'subjects' => $subjects,
            // Grades 11 and 12 exist twice, once per track; offer the swap.
            'siblingTracks' => $grade->track
                ? GradeLevel::where('is_active', true)
                    ->where('stage', GradeLevel::STAGE_SECONDARY)
                    ->whereNotNull('track')
                    ->where('id', '!=', $grade->id)
                    ->where('name_en', 'like', explode(' — ', (string) $grade->name_en)[0] . '%')
                    ->get(['key', 'name', 'track'])
                    ->map(fn (GradeLevel $sibling) => [
                        'key'         => $sibling->key,
                        'name'        => $sibling->name,
                        'track_label' => $sibling->trackLabel(),
                    ])->values()
                : [],
        ]);
    }
}
