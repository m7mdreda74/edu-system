<?php

declare(strict_types=1);

namespace App\Application\Learning\Services;

use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\LearningOutcome;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Support\Collection;

/**
 * Computes explainable learning-outcome progress from the student's records.
 *
 * The same projection is used by the Study Room and the parent's read-only
 * report so the two surfaces cannot drift into different definitions of
 * "completed" or "mastered".
 */
class LearningOutcomeProgressService
{
    /**
     * @param  Collection<int, LearningOutcome>  $outcomes
     * @return Collection<int, array{key: string, percent: int}>
     */
    public function statuses(User $student, Collection $outcomes): Collection
    {
        return $this->statusesForStudents(collect([$student->id]), $outcomes)->get($student->id, collect());
    }

    /**
     * Published outcomes for the student's active subscriptions only.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function summaryForStudent(User $student): Collection
    {
        $assignmentIds = Subscription::active()
            ->forStudent($student->id)
            ->pluck('teaching_assignment_id')
            ->filter()
            ->unique()
            ->values();

        if ($assignmentIds->isEmpty()) {
            return collect();
        }

        $units = CurriculumUnit::query()
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->where('is_published', true)
            ->whereHas('learningOutcomes', fn ($query) => $query->where('is_published', true))
            ->with([
                'assignment.subject:id,name',
                'learningOutcomes' => fn ($query) => $query
                    ->where('is_published', true)
                    ->with([
                        'lesson:id,title,duration_seconds',
                        'quiz:id,title',
                    ])
                    ->orderBy('order'),
            ])
            ->orderBy('order')
            ->get();

        $outcomes = $units->flatMap(fn (CurriculumUnit $unit) => $unit->learningOutcomes);
        $statuses = $this->statuses($student, $outcomes);

        return $units->map(function (CurriculumUnit $unit) use ($statuses): array {
            $unitOutcomes = $unit->learningOutcomes->map(function (LearningOutcome $outcome) use ($statuses): array {
                $status = $statuses->get($outcome->id, ['key' => 'not_started', 'percent' => 0]);

                return [
                    'id' => $outcome->id,
                    'title' => $outcome->title,
                    'description' => $outcome->description,
                    'target_type' => $this->targetType($outcome),
                    'target_title' => $outcome->lesson?->title ?? $outcome->quiz?->title,
                    'status' => $status,
                ];
            })->values();

            return [
                'id' => $unit->id,
                'title' => $unit->title,
                'subject' => $unit->assignment?->subject?->only(['id', 'name']),
                'outcomes' => $unitOutcomes,
                'total_count' => $unitOutcomes->count(),
                'completed_count' => $unitOutcomes
                    ->filter(fn (array $outcome): bool => in_array($outcome['status']['key'], ['completed', 'mastered'], true))
                    ->count(),
                'progress_percent' => $unitOutcomes->isEmpty()
                    ? 0
                    : (int) round($unitOutcomes->avg('status.percent')),
            ];
        })->values();
    }

    /**
     * Aggregates the same outcome statuses across a teacher's active roster.
     * The query is intentionally scoped by assignment IDs supplied by the
     * teacher analytics service, which has already enforced ownership.
     *
     * @param  Collection<int, int>  $studentIds
     * @param  Collection<int, int>  $assignmentIds
     * @return Collection<int, array<string, mixed>>
     */
    public function summaryForTeacher(Collection $studentIds, Collection $assignmentIds): Collection
    {
        if ($studentIds->isEmpty() || $assignmentIds->isEmpty()) {
            return collect();
        }

        $units = CurriculumUnit::query()
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->where('is_published', true)
            ->whereHas('learningOutcomes', fn ($query) => $query->where('is_published', true))
            ->with([
                'assignment.subject:id,name',
                'learningOutcomes' => fn ($query) => $query
                    ->where('is_published', true)
                    ->with([
                        'lesson:id,title,duration_seconds',
                        'quiz:id,title',
                    ])
                    ->orderBy('order'),
            ])
            ->orderBy('order')
            ->get();

        $outcomes = $units->flatMap(fn (CurriculumUnit $unit) => $unit->learningOutcomes);
        $statuses = $this->statusesForStudents($studentIds, $outcomes);

        return $units->map(function (CurriculumUnit $unit) use ($studentIds, $statuses): array {
            return [
                'id' => $unit->id,
                'title' => $unit->title,
                'subject' => $unit->assignment?->subject?->only(['id', 'name']),
                'outcomes' => $unit->learningOutcomes->map(function (LearningOutcome $outcome) use ($studentIds, $statuses): array {
                    $outcomeStatuses = $studentIds
                        ->map(fn ($studentId) => $statuses->get($studentId, collect())->get($outcome->id, ['key' => 'not_started', 'percent' => 0]));

                    return [
                        'id' => $outcome->id,
                        'title' => $outcome->title,
                        'target_type' => $this->targetType($outcome),
                        'target_title' => $outcome->lesson?->title ?? $outcome->quiz?->title,
                        'student_count' => $outcomeStatuses->count(),
                        'completed_count' => $outcomeStatuses->filter(
                            fn (array $status): bool => in_array($status['key'], ['completed', 'mastered'], true),
                        )->count(),
                        'mastered_count' => $outcomeStatuses->where('key', 'mastered')->count(),
                        'in_progress_count' => $outcomeStatuses->where('key', 'in_progress')->count(),
                        'not_started_count' => $outcomeStatuses->where('key', 'not_started')->count(),
                        'progress_percent' => $outcomeStatuses->isEmpty()
                            ? 0
                            : (int) round($outcomeStatuses->avg('percent')),
                    ];
                })->values(),
            ];
        })->values();
    }

    /**
     * @param  Collection<int, int>  $studentIds
     * @param  Collection<int, LearningOutcome>  $outcomes
     * @return Collection<int, Collection<int, array{key: string, percent: int}>>
     */
    private function statusesForStudents(Collection $studentIds, Collection $outcomes): Collection
    {
        if ($studentIds->isEmpty() || $outcomes->isEmpty()) {
            return collect();
        }

        $lessonIds = $outcomes->pluck('group_material_id')->filter()->unique()->values();
        $quizIds = $outcomes->pluck('quiz_id')->filter()->unique()->values();

        $progress = LessonProgress::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $items): Collection => $items->keyBy('lesson_id'));

        $attempts = QuizAttempt::query()
            ->whereIn('user_id', $studentIds)
            ->whereIn('quiz_id', $quizIds)
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $items): Collection => $items->groupBy('quiz_id'));

        return $studentIds->mapWithKeys(fn ($studentId): array => [
            $studentId => $outcomes->mapWithKeys(fn (LearningOutcome $outcome): array => [
                $outcome->id => $this->status(
                    $outcome,
                    $progress->get($studentId, collect()),
                    $attempts->get($studentId, collect()),
                ),
            ]),
        ]);
    }

    /**
     * @param  Collection<int, LessonProgress>  $progress
     * @param  Collection<int, Collection<int, QuizAttempt>>  $attempts
     * @return array{key: string, percent: int}
     */
    private function status(LearningOutcome $outcome, Collection $progress, Collection $attempts): array
    {
        if ($outcome->group_material_id !== null) {
            $seen = $progress->get($outcome->group_material_id);
            if ($seen?->is_completed) {
                return ['key' => 'completed', 'percent' => 100];
            }

            if (($seen?->watched_seconds ?? 0) > 0) {
                $duration = (int) ($outcome->lesson?->duration_seconds ?? 0);

                return [
                    'key' => 'in_progress',
                    'percent' => $duration > 0
                        ? min(99, (int) round(((int) $seen->watched_seconds / $duration) * 100))
                        : 1,
                ];
            }

            return ['key' => 'not_started', 'percent' => 0];
        }

        if ($outcome->quiz_id !== null) {
            $best = ($attempts->get($outcome->quiz_id) ?? collect())
                ->whereNotNull('submitted_at')
                ->sortByDesc('score')
                ->first();

            if ($best?->passed) {
                return ['key' => 'mastered', 'percent' => 100];
            }

            if ($best !== null) {
                return ['key' => 'in_progress', 'percent' => min(99, (int) ($best->score ?? 0))];
            }
        }

        return ['key' => 'not_started', 'percent' => 0];
    }

    private function targetType(LearningOutcome $outcome): string
    {
        return $outcome->group_material_id !== null
            ? 'lesson'
            : ($outcome->quiz_id !== null ? 'quiz' : 'unit');
    }
}
