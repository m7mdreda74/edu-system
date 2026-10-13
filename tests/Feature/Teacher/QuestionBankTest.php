<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Quiz\Models\QuestionBankQuestion;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a reusable question and copies a snapshot into the quiz builder', function (): void {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');
    $subject = Subject::query()->firstOrFail();
    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
    ]);
    $lesson = GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);
    $quiz = Quiz::factory()->create(['curriculum_unit_id' => $unit->id]);

    $this->actingAs($teacher)
        ->post(route('teacher.question-bank.store'), [
            'subject_id' => $subject->id,
            'curriculum_unit_id' => $unit->id,
            'lesson_id' => $lesson->id,
            'topic' => 'الكسور',
            'difficulty' => 'hard',
            'visibility' => 'private',
            'question_text' => 'ما الكسر المكافئ للنصف؟',
            'type' => 'multiple',
            'points' => 5,
            'explanation' => 'نضرب البسط والمقام في العدد نفسه.',
            'tags' => ['fractions', 'review'],
            'options' => [
                ['option_text' => '2/4', 'is_correct' => true],
                ['option_text' => '3/4', 'is_correct' => false],
                ['option_text' => '4/8', 'is_correct' => true],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $bankQuestion = QuestionBankQuestion::with('options')->firstOrFail();

    expect($bankQuestion->teacher_id)->toBe($teacher->id)
        ->and($bankQuestion->subject_id)->toBe($subject->id)
        ->and($bankQuestion->curriculum_unit_id)->toBe($unit->id)
        ->and($bankQuestion->lesson_id)->toBe($lesson->id)
        ->and($bankQuestion->tags)->toBe(['fractions', 'review'])
        ->and($bankQuestion->options)->toHaveCount(3);

    $this->actingAs($teacher)
        ->get(route('teacher.question-bank'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/QuestionBank')
            ->where('questions.data.0.id', $bankQuestion->id)
            ->where('questions.total', 1)
            ->where('questions.data.0.can_edit', true)
            ->where('questions.data.0.options.0.is_correct', true));

    $this->actingAs($teacher)
        ->get(route('teacher.quizzes.edit', $quiz->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/QuizBuilder')
            ->where('bank_questions.0.id', $bankQuestion->id)
            ->where('bank_questions.0.attached', false));

    $this->actingAs($teacher)
        ->post(route('teacher.quizzes.questions.from-bank', [
            'quiz' => $quiz->id,
            'bankQuestion' => $bankQuestion->id,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $quizQuestion = $quiz->questions()->with('options')->firstOrFail();

    expect($quizQuestion->question_bank_question_id)->toBe($bankQuestion->id)
        ->and($quizQuestion->question_text)->toBe('ما الكسر المكافئ للنصف؟')
        ->and($quizQuestion->points)->toBe(5)
        ->and($quizQuestion->options)->toHaveCount(3)
        ->and($quizQuestion->options->where('is_correct', true))->toHaveCount(2);

    $bankQuestion->update(['question_text' => 'نسخة محدثة في البنك']);

    expect($quizQuestion->fresh()->question_text)->toBe('ما الكسر المكافئ للنصف؟');

    $bankQuestion->delete();

    expect($quizQuestion->fresh()->question_bank_question_id)->toBeNull()
        ->and($quizQuestion->fresh()->question_text)->toBe('ما الكسر المكافئ للنصف؟');
});

it('keeps private questions isolated and allows explicit shared-library reuse', function (): void {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');
    $otherTeacher = User::factory()->create();
    $otherTeacher->assignRole('teacher');
    $subject = Subject::query()->firstOrFail();
    $grade = GradeLevel::query()->firstOrFail();

    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'grade_level_id' => $grade->id,
    ]);
    $otherAssignment = TeachingAssignment::factory()->create([
        'teacher_id' => $otherTeacher->id,
        'subject_id' => $subject->id,
        'grade_level_id' => $grade->id,
    ]);
    $otherUnit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $otherAssignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
    ]);
    $otherQuiz = Quiz::factory()->create(['curriculum_unit_id' => $otherUnit->id]);
    $bankQuestion = QuestionBankQuestion::create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'question_text' => 'سؤال خاص بالمعلم الأول',
        'type' => 'single',
        'points' => 2,
        'difficulty' => 'medium',
        'visibility' => 'private',
    ]);
    $bankQuestion->options()->createMany([
        ['option_text' => 'صحيح', 'is_correct' => true],
        ['option_text' => 'خطأ', 'is_correct' => false],
    ]);

    $this->actingAs($otherTeacher)
        ->put(route('teacher.question-bank.update', $bankQuestion->id), [
            'question_text' => 'محاولة تعديل',
            'type' => 'single',
            'difficulty' => 'medium',
            'visibility' => 'private',
            'options' => [
                ['option_text' => 'صحيح', 'is_correct' => true],
                ['option_text' => 'خطأ', 'is_correct' => false],
            ],
        ])
        ->assertForbidden();

    $this->actingAs($otherTeacher)
        ->delete(route('teacher.question-bank.destroy', $bankQuestion->id))
        ->assertForbidden();

    $this->actingAs($otherTeacher)
        ->post(route('teacher.quizzes.questions.from-bank', [
            'quiz' => $otherQuiz->id,
            'bankQuestion' => $bankQuestion->id,
        ]))
        ->assertForbidden();

    $this->actingAs($otherTeacher)
        ->get(route('teacher.question-bank'))
        ->assertInertia(fn ($page) => $page
            ->where('questions.data', fn ($questions): bool => collect($questions)->isEmpty()));

    $bankQuestion->update(['visibility' => 'shared']);

    $this->actingAs($otherTeacher)
        ->get(route('teacher.question-bank'))
        ->assertInertia(fn ($page) => $page
            ->where('questions.data.0.id', $bankQuestion->id)
            ->where('questions.data.0.can_edit', false)
            ->where('questions.data.0.can_delete', false));

    $this->actingAs($otherTeacher)
        ->post(route('teacher.quizzes.questions.from-bank', [
            'quiz' => $otherQuiz->id,
            'bankQuestion' => $bankQuestion->id,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($otherQuiz->questions()->where('question_bank_question_id', $bankQuestion->id)->exists())->toBeTrue();
});
