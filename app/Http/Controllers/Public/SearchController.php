<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Navbar autocomplete. Searches the two things a visitor actually looks for:
 * a teacher by name, or a subject to find teachers in.
 */
class SearchController extends Controller
{
    private const LIMIT = 6;

    public function autocomplete(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $like = '%' . $query . '%';

        $teachers = User::role('teacher')
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('headline', 'like', $like))
            ->take(self::LIMIT)
            ->get(['id', 'name', 'headline', 'avatar'])
            ->map(fn (User $teacher) => [
                'type'     => 'teacher',
                'id'       => $teacher->id,
                'title'    => $teacher->name,
                'subtitle' => $teacher->headline ?? 'معلم',
                'url'      => route('teachers.show', $teacher->id),
            ]);

        // A subject is only useful to a visitor when a teacher covers it.
        $subjects = Subject::where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('name_en', 'like', $like))
            ->take(self::LIMIT)
            ->get(['id', 'name', 'grade_level'])
            ->map(function (Subject $subject) {
                $assignment = TeachingAssignment::with('gradeLevel:id,key')
                    ->where('subject_id', $subject->id)
                    ->where('is_active', true)
                    ->first();

                if (! $assignment?->gradeLevel) {
                    return null;
                }

                return [
                    'type'     => 'subject',
                    'id'       => $subject->id,
                    'title'    => $subject->name,
                    'subtitle' => 'مادة دراسية',
                    'url'      => route('subjects.teachers', [
                        'gradeKey' => $assignment->gradeLevel->key,
                        'subject'  => $subject->id,
                    ]),
                ];
            })
            ->filter()
            ->values();

        return response()->json($teachers->concat($subjects)->take(self::LIMIT * 2)->values());
    }
}
