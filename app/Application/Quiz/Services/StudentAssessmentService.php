<?php

declare(strict_types=1);

namespace App\Application\Quiz\Services;

use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Support\Collection;

class StudentAssessmentService
{
    /**
     * Build the student's assessment center from published quizzes unlocked by
     * a current subscription. Questions and answer keys are never included.
     *
     * @return array{items: Collection<int, array<string, mixed>>, summary: array<string, int|float>}
     */
    public function forStudent(User $student): array
    {
        $assignmentIds = Subscription::active()
            ->where('student_id', $student->id)
            ->pluck('teaching_assignment_id')
            ->filter()
            ->unique()
            ->values();

        if ($assignmentIds->isEmpty()) {
            return ['items' => collect(), 'summary' => $this->emptySummary()];
        }

        $quizzes = Quiz::query()
            ->where('is_active', true)
            ->whereHas('unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->with([
                'unit:id,teaching_assignment_id,title,order',
                'unit.assignment.subject:id,name',
                'unit.assignment.teacher:id,name',
                'questions:id,quiz_id,points',
                'attempts' => fn ($query) => $query
                    ->where('user_id', $student->id)
                    ->latest('created_at'),
            ])
            ->latest('created_at')
            ->limit(100)
            ->get();

        $items = $quizzes
            ->map(fn (Quiz $quiz): array => $this->present($quiz))
            ->values();

        return [
            'items' => $items,
            'summary' => $this->summarize($items),
        ];
    }

    /** @return array<string, int|float> */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'available' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'passed' => 0,
            'average_score' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function present(Quiz $quiz): array
    {
        $attempts = $quiz->attempts;
        $submitted = $attempts->filter(fn (QuizAttempt $attempt): bool => $attempt->submitted_at !== null);
        $latest = $attempts->first();
        $best = $submitted->sortByDesc(fn (QuizAttempt $attempt): int => (int) ($attempt->score ?? 0))->first();
        $questionCount = $quiz->questions->count();
        $remainingAttempts = max(0, QuizService::MAX_ATTEMPTS - $attempts->count());

        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'subject' => $quiz->unit?->assignment?->subject?->only(['id', 'name']),
            'teacher' => $quiz->unit?->assignment?->teacher?->only(['id', 'name']),
            'unit' => $quiz->unit?->only(['id', 'title', 'order']),
            'questions_count' => $questionCount,
            'total_points' => (int) $quiz->questions->sum(fn ($question): int => max(1, (int) $question->points)),
            'passing_score' => (int) $quiz->passing_score,
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'available_from' => $quiz->available_from?->toIso8601String(),
            'available_until' => $quiz->available_until?->toIso8601String(),
            'is_open' => $quiz->isOpen(),
            'status' => $this->status($quiz, $latest, $questionCount),
            'can_start' => $quiz->isOpen() && $questionCount > 0 && $remainingAttempts > 0,
            'attempts_used' => $attempts->count(),
            'remaining_attempts' => $remainingAttempts,
            'latest_attempt' => $latest ? $this->attempt($latest) : null,
            'best_attempt' => $best ? $this->attempt($best) : null,
            'attempts' => $attempts->map(fn (QuizAttempt $attempt): array => $this->attempt($attempt))->values(),
        ];
    }

    private function status(Quiz $quiz, ?QuizAttempt $latest, int $questionCount): string
    {
        if ($questionCount === 0) {
            return 'not_ready';
        }

        if ($latest?->submitted_at === null && $latest?->started_at !== null) {
            return 'in_progress';
        }

        if ($quiz->available_from?->isFuture()) {
            return 'upcoming';
        }

        if ($quiz->available_until?->isPast()) {
            return 'closed';
        }

        if ($latest?->submitted_at !== null) {
            return 'completed';
        }

        return 'available';
    }

    /** @return array<string, mixed> */
    private function attempt(QuizAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'score' => $attempt->score,
            'earned_points' => $attempt->earned_points,
            'total_points' => $attempt->total_points,
            'passed' => (bool) $attempt->passed,
            'started_at' => $attempt->started_at?->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'violations' => (int) $attempt->violations,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, int|float>
     */
    private function summarize(Collection $items): array
    {
        $submittedScores = $items
            ->pluck('attempts')
            ->flatten(1)
            ->filter(fn (array $attempt): bool => $attempt['submitted_at'] !== null)
            ->pluck('score')
            ->filter(fn ($score): bool => $score !== null);

        return [
            'total' => $items->count(),
            'available' => $items->where('status', 'available')->count(),
            'in_progress' => $items->where('status', 'in_progress')->count(),
            'completed' => $items->where('status', 'completed')->count(),
            'passed' => $items
                ->pluck('attempts')
                ->flatten(1)
                ->filter(fn (array $attempt): bool => $attempt['submitted_at'] !== null && $attempt['passed'])
                ->count(),
            'average_score' => $submittedScores->isEmpty()
                ? 0
                : (int) round((float) $submittedScores->avg()),
        ];
    }
}
