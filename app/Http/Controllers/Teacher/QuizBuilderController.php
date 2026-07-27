<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizOption;
use App\Domain\Quiz\Models\QuizQuestion;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\QuizQuestionRequest;
use App\Http\Requests\Teacher\QuizSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "المدرس يقدر يعمل كويزات إلكترونية ويحدد مدتها ومتاح من/إلى، ويضيف الأسئلة
 * وإجاباتها كاختيار من متعدد" — the electronic form of the unit exam.
 *
 * A question and its options travel in one request and are written in one
 * transaction, so the answer key is never half-saved. Editing rewrites the
 * options wholesale instead of diffing them: an attempt records a score, never
 * the option a student ticked, so no row outlives the question it belongs to.
 */
class QuizBuilderController extends Controller
{
    public function edit(int $quizId): Response
    {
        $quiz = $this->ownedQuizOrFail($quizId, [
            'questions.options',
            'material:id,title',
            'unit.term:id,name,term_number',
            'unit.assignment.subject:id,name',
            'unit.assignment.gradeLevel:id,key,name',
        ]);

        $unit       = $quiz->unit;
        $assignment = $unit->assignment;

        return Inertia::render('Teacher/QuizBuilder', [
            'quiz' => [
                'id'                 => $quiz->id,
                'title'              => $quiz->title,
                'time_limit_minutes' => $quiz->time_limit_minutes,
                'passing_score'      => $quiz->passing_score,
                'is_active'          => $quiz->is_active,
                // Shaped for <input type="datetime-local">, which refuses anything else.
                'available_from'     => $quiz->available_from?->format('Y-m-d\TH:i'),
                'available_until'    => $quiz->available_until?->format('Y-m-d\TH:i'),
                'window_label'       => $quiz->windowLabel(),
                'is_open'            => $quiz->isOpen(),
                'is_unit_exam'       => $quiz->lesson_id === null,
                'lesson'             => $quiz->material?->only(['id', 'title']),
                'attempts_count'     => $quiz->attempts()->count(),
            ],
            'unit' => [
                'id'      => $unit->id,
                'order'   => $unit->order,
                'title'   => $unit->title,
                'term_id' => $unit->academic_term_id,
                'term'    => $unit->term?->only(['id', 'name']),
            ],
            'assignment' => [
                'id'      => $assignment->id,
                'subject' => $assignment->subject?->only(['id', 'name']),
                'grade'   => $assignment->gradeLevel?->only(['key', 'name']),
            ],
            'questions' => $quiz->questions->map(fn (QuizQuestion $question): array => [
                'id'            => $question->id,
                'question_text' => $question->question_text,
                'type'          => $question->type,
                'order'         => $question->order,
                'options'       => $question->options->map(fn (QuizOption $option): array => [
                    'id'          => $option->id,
                    'option_text' => $option->option_text,
                    'is_correct'  => $option->is_correct,
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(QuizSettingsRequest $request, int $unitId): RedirectResponse
    {
        $unit = CurriculumUnit::with('assignment')->findOrFail($unitId);

        $this->assertOwns($unit->assignment);

        $lessonId = $request->integer('lesson_id') ?: null;

        if ($lessonId !== null && ! $unit->lessons()->whereKey($lessonId)->exists()) {
            return back()->with('error', 'هذا الدرس لا ينتمي إلى هذه الوحدة.');
        }

        // The unit exam is the quiz standing on its own, and the unit can only
        // point at one of them; a second would silently hide the first.
        if ($lessonId === null && $unit->electronicExam()->exists()) {
            return back()->with('error', 'هذه الوحدة لديها نموذج إلكتروني بالفعل.');
        }

        $quiz = Quiz::create([
            ...$request->settings(),
            'curriculum_unit_id' => $unit->id,
            'lesson_id'          => $lessonId,
        ]);

        return redirect()
            ->route('teacher.quizzes.edit', $quiz->id)
            ->with('success', 'تم إنشاء الاختبار. أضف الأسئلة الآن.');
    }

    public function update(QuizSettingsRequest $request, int $quizId): RedirectResponse
    {
        $quiz = $this->ownedQuizOrFail($quizId);

        $quiz->update($request->settings());

        return back()->with('success', 'تم حفظ إعدادات الاختبار.');
    }

    public function destroy(int $quizId): RedirectResponse
    {
        $quiz = $this->ownedQuizOrFail($quizId);
        $unit = $quiz->unit;

        $quiz->delete();

        // The builder page dies with the quiz, so land on the syllabus instead
        // of bouncing back to a page that no longer resolves.
        return redirect()
            ->route('teacher.curriculum', [$unit->teaching_assignment_id, 'term' => $unit->academic_term_id])
            ->with('success', 'تم حذف الاختبار.');
    }

    public function storeQuestion(QuizQuestionRequest $request, int $quizId): RedirectResponse
    {
        $quiz = $this->ownedQuizOrFail($quizId);

        DB::transaction(function () use ($quiz, $request): void {
            $question = $quiz->questions()->create([
                'question_text' => $request->validated('question_text'),
                'type'          => $request->validated('type'),
                'order'         => $request->integer('order') ?: (int) $quiz->questions()->max('order') + 1,
            ]);

            $this->replaceOptions($question, $request->validated('options'));
        });

        return back()->with('success', 'تمت إضافة السؤال بنجاح.');
    }

    public function updateQuestion(QuizQuestionRequest $request, int $questionId): RedirectResponse
    {
        $question = $this->ownedQuestionOrFail($questionId);

        DB::transaction(function () use ($question, $request): void {
            $question->update([
                'question_text' => $request->validated('question_text'),
                'type'          => $request->validated('type'),
                'order'         => $request->integer('order') ?: $question->order,
            ]);

            $this->replaceOptions($question, $request->validated('options'));
        });

        return back()->with('success', 'تم حفظ السؤال بنجاح.');
    }

    public function destroyQuestion(int $questionId): RedirectResponse
    {
        $question = $this->ownedQuestionOrFail($questionId);

        $question->delete();

        return back()->with('success', 'تم حذف السؤال بنجاح.');
    }

    // ─── Internals ────────────────────────────────────────────────

    /**
     * @param array<int, array{option_text: string, is_correct: bool|int|string}> $options
     */
    private function replaceOptions(QuizQuestion $question, array $options): void
    {
        $question->options()->delete();

        $question->options()->createMany(array_map(
            static fn (array $option): array => [
                'option_text' => $option['option_text'],
                'is_correct'  => (bool) $option['is_correct'],
            ],
            array_values($options),
        ));
    }

    /** @param array<int, string> $with */
    private function ownedQuizOrFail(int $quizId, array $with = []): Quiz
    {
        $quiz = Quiz::with(['unit.assignment', ...$with])->findOrFail($quizId);

        $this->assertOwns($quiz->unit?->assignment);

        return $quiz;
    }

    private function ownedQuestionOrFail(int $questionId): QuizQuestion
    {
        $question = QuizQuestion::with('quiz.unit.assignment')->findOrFail($questionId);

        $this->assertOwns($question->quiz?->unit?->assignment);

        return $question;
    }

    /** A quiz hangs off a unit of the syllabus, so ownership is the assignment's. */
    private function assertOwns(?TeachingAssignment $assignment): void
    {
        abort_unless(
            $assignment && $assignment->teacher_id === Auth::id(),
            403,
            'هذا التكليف ليس ضمن جدولك.',
        );
    }
}
