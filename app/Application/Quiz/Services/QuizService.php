<?php

declare(strict_types=1);

namespace App\Application\Quiz\Services;

use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * QuizService — Application Layer
 * All scoring is done server-side. Client only submits answer IDs.
 */
class QuizService
{
    const MAX_ATTEMPTS = 3;

    /**
     * Start a quiz attempt.
     * Records started_at for server-side time limit enforcement.
     *
     * @throws LogicException
     */
    public function startAttempt(User $user, Quiz $quiz): QuizAttempt
    {
        if (! $quiz->is_active) {
            throw new LogicException('هذا الاختبار غير متاح حالياً.');
        }

        $attemptsCount = QuizAttempt::where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->count();

        if ($attemptsCount >= self::MAX_ATTEMPTS) {
            throw new LogicException('لقد استنفذت جميع محاولاتك لهذا الاختبار.');
        }

        return DB::transaction(fn () => QuizAttempt::create([
            'user_id'    => $user->id,
            'quiz_id'    => $quiz->id,
            'started_at' => now(),
            'score'      => 0,
            'passed'     => false,
        ]));
    }

    /**
     * Submit answers and grade server-side.
     * answers = [question_id => [option_id, ...], ...]
     *
     * @param array<int, array<int>> $answers
     *
     * @throws LogicException
     */
    public function submitAttempt(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        // Guard: already submitted
        if ($attempt->isSubmitted()) {
            throw new LogicException('تم تسليم هذا الاختبار مسبقاً.');
        }

        // Guard: time limit exceeded (server-side enforcement)
        if ($attempt->isTimedOut()) {
            return $this->forceSubmit($attempt, $answers);
        }

        return $this->grade($attempt, $answers);
    }

    private function forceSubmit(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        // Grade whatever was answered so far — no bonus for time violations
        return $this->grade($attempt, $answers);
    }

    /**
     * Core grading logic — never trusts client-sent scores.
     *
     * @param array<int, array<int>> $answers
     */
    private function grade(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        $quiz      = $attempt->quiz->load('questions.options');
        $questions = $quiz->questions;
        $total     = $questions->count();

        if ($total === 0) {
            throw new LogicException('الاختبار لا يحتوي على أسئلة.');
        }

        $earnedPoints = 0;
        $totalPoints = (int) $questions->sum(fn ($question): int => max(1, (int) $question->points));

        foreach ($questions as $question) {
            $submittedOptionIds = collect($answers[$question->id] ?? [])
                ->map(fn($id) => (int) $id)
                ->toArray();

            $correctOptionIds = $question->correctOptions
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->toArray();

            // For single/true-false: exact match
            // For multiple: ALL correct options must be selected, no wrong ones
            $isCorrect = count($submittedOptionIds) > 0
                && empty(array_diff($submittedOptionIds, $correctOptionIds))
                && empty(array_diff($correctOptionIds, $submittedOptionIds));

            if ($isCorrect) {
                $earnedPoints += max(1, (int) $question->points);
            }
        }

        // The student earns each question's configured points. The percentage
        // remains the stable value used by the passing-score rule.
        $scorePercent = (int) round(($earnedPoints / $totalPoints) * 100);
        $passed       = $scorePercent >= $quiz->passing_score;

        return DB::transaction(function () use ($attempt, $scorePercent, $passed, $earnedPoints, $totalPoints) {
            $attempt->update([
                'score'        => $scorePercent,
                'earned_points' => $earnedPoints,
                'total_points'  => $totalPoints,
                'passed'       => $passed,
                'submitted_at' => now(),
            ]);

            return $attempt->fresh();
        });
    }

    public function getAttempts(User $user, Quiz $quiz): \Illuminate\Database\Eloquent\Collection
    {
        return QuizAttempt::where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getRemainingAttempts(User $user, Quiz $quiz): int
    {
        $used = QuizAttempt::where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->count();

        return max(0, self::MAX_ATTEMPTS - $used);
    }
}
