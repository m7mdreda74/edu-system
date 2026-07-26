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
 * Step two of the browse flow: a grade opens the subjects taught in it.
 * Only subjects with at least one active teacher are shown — an empty subject
 * page is a dead end for the visitor.
 */
class GradeLevelBrowseController extends Controller
{
    public function show(string $key): Response
    {
        $grade = GradeLevel::where('key', $key)
            ->where('is_active', true)
            ->firstOrFail();

        $assignments = TeachingAssignment::where('grade_level_id', $grade->id)
            ->where('is_active', true)
            ->get(['id', 'subject_id', 'teacher_id']);

        $subjects = Subject::whereIn('id', $assignments->pluck('subject_id')->unique())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'name_en', 'icon'])
            ->map(fn (Subject $subject) => [
                'id'             => $subject->id,
                'name'           => $subject->name,
                'name_en'        => $subject->name_en,
                'icon'           => $subject->icon,
                'teachers_count' => $assignments->where('subject_id', $subject->id)
                    ->pluck('teacher_id')
                    ->unique()
                    ->count(),
            ])
            ->values();

        return Inertia::render('Public/GradeSubjects', [
            'grade'    => [
                'id'      => $grade->id,
                'key'     => $grade->key,
                'name'    => $grade->name,
                'name_en' => $grade->name_en,
                'stage'   => $grade->stage,
            ],
            'subjects' => $subjects,
        ]);
    }
}
