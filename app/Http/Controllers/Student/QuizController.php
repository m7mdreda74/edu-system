<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Quiz\Services\QuizService;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\User\Models\User;
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

    /**
     * Deliberately renders outside the exam's window too: a student who arrives
     * early should read "متاح من …" rather than be shown a 403.
     */
    public function show(int $quizId): Response
    {
        /** @var User $user */
        $user = auth()->user();
        $quiz = Quiz::with('unit:id,teaching_assignment_id')
            ->withCount('questions')
            ->where('is_active', true)
            ->findOrFail($quizId);

        $this->authorizeAccess($user, $quiz);

        $attempts          = $this->quizService->getAttempts($user, $quiz);
        $remainingAttempts = $this->quizService->getRemainingAttempts($user, $quiz);

        return Inertia::render('Student/Quiz', [
            'quiz' => [
                'id'                 => $quiz->id,
                'title'              => $quiz->title,
                'time_limit_minutes' => $quiz->time_limit_minutes,
                'passing_score'      => $quiz->passing_score,
                'questions_count'    => (int) $quiz->questions_count,
                'is_open'            => $quiz->isOpen(),
                'opens_at'           => $quiz->available_from?->toIso8601String(),
                'closes_at'          => $quiz->available_until?->toIso8601String(),
                'window_label'       => $quiz->windowLabel(),
            ],
            // Question text is deliberately withheld until `start` has checked
            // the availability window and created the student's attempt.
            'questions'         => [],
            'attempts'          => $attempts,
            'remainingAttempts' => $remainingAttempts,
        ]);
    }

    public function start(int $quizId): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $quiz = Quiz::with([
            'unit:id,teaching_assignment_id',
            'questions.options:id,question_id,option_text',
        ])->findOrFail($quizId);

        $this->authorizeAccess($user, $quiz);

        // "متاح من / إلى" is the teacher's own gate on the exam.
        abort_unless($quiz->isOpen(), 403, 'هذا الاختبار غير متاح حالياً.');
        abort_if($quiz->questions->isEmpty(), 422, 'الاختبار لا يحتوي على أسئلة.');

        try {
            $attempt = $this->quizService->startAttempt($user, $quiz);

            return response()->json([
                'attempt_id' => $attempt->id,
                'started_at' => $attempt->started_at->toIso8601String(),
                // `is_correct` was not selected by the relationship above and
                // is never serialized to the browser.
                'questions'  => $this->presentQuestions($quiz),
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
        /** @var User $user */
        $user = auth()->user();
        $quiz = Quiz::with('unit:id,teaching_assignment_id')->findOrFail($quizId);

        $this->authorizeAccess($user, $quiz);

        $validated = $request->validate([
            'attempt_id'          => ['required', 'integer'],
            'answers'             => ['required', 'array'],
            'answers.*'           => ['array'],
            'answers.*.*'         => ['integer'],
        ]);

        $attempt = \App\Domain\Quiz\Models\QuizAttempt::where('id', $validated['attempt_id'])
            ->where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
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
        /** @var User $user */
        $user = auth()->user();
        $quiz = Quiz::with('unit:id,teaching_assignment_id')->findOrFail($quizId);

        $this->authorizeAccess($user, $quiz);

        $validated = $request->validate([
            'attempt_id' => ['required', 'integer'],
        ]);

        $attempt = \App\Domain\Quiz\Models\QuizAttempt::where('id', $validated['attempt_id'])
            ->where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->firstOrFail();

        $attempt->increment('violations');

        return response()->json([
            'violations' => $attempt->violations,
        ]);
    }

    /**
     * Quizzes belong to a unit of the syllabus; a live subscription to that
     * teacher's subject unlocks them, whether the student sits in a group or
     * takes private lessons.
     */
    private function authorizeAccess(User $user, Quiz $quiz): void
    {
        $assignmentId = $quiz->unit?->teaching_assignment_id;

        abort_unless(
            $assignmentId && $user->hasActiveSubscriptionToAssignment($assignmentId),
            403,
            'يجب أن يكون لديك اشتراك فعّال في هذه المجموعة.',
        );
    }

    /**
     * Questions are disclosed only after the attempt has started. The query in
     * `start` selects no answer-key column, so even accidental serialization of
     * an option cannot reveal whether it is correct.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presentQuestions(Quiz $quiz): array
    {
        return $quiz->questions->map(fn ($question): array => [
            'id'   => $question->id,
            'text' => $question->question_text,
            'type' => $question->type,
            'options' => $question->options->map(fn ($option): array => [
                'id'   => $option->id,
                'text' => $option->option_text,
            ])->values()->all(),
        ])->values()->all();
    }
}
