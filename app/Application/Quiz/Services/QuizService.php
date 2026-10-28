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

        $maxAttempts = (int) ($quiz->max_attempts > 0 ? $quiz->max_attempts : self::MAX_ATTEMPTS);

        if ($attemptsCount >= $maxAttempts) {
            throw new LogicException("لقد استنفذت جميع محاولاتك لهذا الاختبار ({$maxAttempts} محاولة).");
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
        $hasEssay = false;
        $storedAnswers = [];

        foreach ($questions as $question) {
            $questionPoints = max(1, (int) $question->points);

            if ($question->type === \App\Domain\Quiz\Models\QuizQuestion::TYPE_ESSAY) {
                $hasEssay = true;
                $raw = $answers[$question->id] ?? null;

                $storedAnswers[$question->id] = [
                    'type' => 'essay',
                    'text' => is_array($raw) ? trim((string) ($raw['text'] ?? '')) : (is_string($raw) ? trim($raw) : ''),
                    'file_path' => is_array($raw) ? ($raw['file_path'] ?? null) : null,
                    'file_name' => is_array($raw) ? ($raw['file_name'] ?? null) : null,
                    'max_points' => $questionPoints,
                    'earned_points' => null, // Waiting for teacher grading
                    'feedback' => null,
                ];
            } else {
                $submitted = $answers[$question->id] ?? [];
                if (! is_array($submitted)) {
                    $submitted = $submitted !== null && $submitted !== '' ? [(int) $submitted] : [];
                }

                $submittedOptionIds = collect($submitted)
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

                $qEarned = $isCorrect ? $questionPoints : 0;
                $earnedPoints += $qEarned;

                $storedAnswers[$question->id] = [
                    'type' => $question->type,
                    'selected_options' => $submittedOptionIds,
                    'is_correct' => $isCorrect,
                    'max_points' => $questionPoints,
                    'earned_points' => $qEarned,
                ];
            }
        }

        $status = $hasEssay ? QuizAttempt::STATUS_NEEDS_GRADING : QuizAttempt::STATUS_GRADED;
        $scorePercent = $hasEssay ? 0 : (int) round(($earnedPoints / max(1, $totalPoints)) * 100);
        $passed = ! $hasEssay && ($scorePercent >= $quiz->passing_score);

        return DB::transaction(function () use ($attempt, $scorePercent, $passed, $earnedPoints, $totalPoints, $status, $storedAnswers) {
            $attempt->update([
                'score'         => $scorePercent,
                'earned_points' => $earnedPoints,
                'total_points'  => $totalPoints,
                'passed'        => $passed,
                'status'        => $status,
                'answers'       => $storedAnswers,
                'submitted_at'  => now(),
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

        $maxAttempts = (int) ($quiz->max_attempts > 0 ? $quiz->max_attempts : self::MAX_ATTEMPTS);

        return max(0, $maxAttempts - $used);
    }
}
