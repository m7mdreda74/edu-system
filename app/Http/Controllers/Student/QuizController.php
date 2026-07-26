<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Quiz\Services\QuizService;
use App\Domain\Quiz\Models\Quiz;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class QuizController extends Controller
{
    public function __construct(
        private readonly QuizService $quizService,
    ) {}

    public function show(int $quizId): Response
    {
        $user = auth()->user();
        $quiz = Quiz::with(['questions.options:id,question_id,option_text'])
            ->where('is_active', true)
            ->findOrFail($quizId);

        // Quizzes belong to a group; only a live subscription unlocks them.
        $hasAccess = $quiz->teaching_group_id
            && $user->subscriptions()->active()->where('teaching_group_id', $quiz->teaching_group_id)->exists();

        abort_unless($hasAccess, 403, 'يجب أن يكون لديك اشتراك فعّال في هذه المجموعة.');

        $attempts         = $this->quizService->getAttempts($user, $quiz);
        $remainingAttempts = $this->quizService->getRemainingAttempts($user, $quiz);

        // Strip correct answers from questions — never sent to client!
        $questions = $quiz->questions->map(fn($q) => [
            'id'   => $q->id,
            'text' => $q->question_text,
            'type' => $q->type,
            // Only send option text + ID — is_correct is NEVER included
            'options' => $q->options->map(fn($o) => [
                'id'   => $o->id,
                'text' => $o->option_text,
            ]),
        ]);

        return Inertia::render('Student/Quiz', [
            'quiz' => [
                'id'                 => $quiz->id,
                'title'              => $quiz->title,
                'time_limit_minutes' => $quiz->time_limit_minutes,
                'passing_score'      => $quiz->passing_score,
                'questions_count'    => $quiz->questions->count(),
            ],
            'questions'         => $questions,
            'attempts'          => $attempts,
            'remainingAttempts' => $remainingAttempts,
        ]);
    }

    public function start(int $quizId): JsonResponse
    {
        $user = auth()->user();
        $quiz = Quiz::findOrFail($quizId);

        try {
            $attempt = $this->quizService->startAttempt($user, $quiz);

            return response()->json([
                'attempt_id' => $attempt->id,
                'started_at' => $attempt->started_at->toIso8601String(),
            ]);
        } catch (LogicException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Submit quiz answers — graded 100% server-side.
     * Client sends: { attempt_id, answers: { question_id: [option_id, ...] } }
     */
    public function submit(Request $request, int $quizId): JsonResponse
    {
        $validated = $request->validate([
            'attempt_id'          => ['required', 'integer'],
            'answers'             => ['required', 'array'],
            'answers.*'           => ['array'],
            'answers.*.*'         => ['integer'],
        ]);

        $user    = auth()->user();
        $attempt = \App\Domain\Quiz\Models\QuizAttempt::where('id', $validated['attempt_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            $result = $this->quizService->submitAttempt($attempt, $validated['answers']);

            return response()->json([
                'score'         => $result->score,
                'passed'        => $result->passed,
                'passing_score' => $attempt->quiz->passing_score,
            ]);
        } catch (LogicException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function recordViolation(Request $request, int $quizId): JsonResponse
    {
        $validated = $request->validate([
            'attempt_id' => ['required', 'integer'],
        ]);

        $attempt = \App\Domain\Quiz\Models\QuizAttempt::where('id', $validated['attempt_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $attempt->increment('violations');

        return response()->json([
            'violations' => $attempt->violations,
        ]);
    }
}
