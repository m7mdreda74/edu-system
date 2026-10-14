<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('notifies the student and verified parent once when quiz feedback is published', function (): void {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');
    $student = User::factory()->create();
    $parent = User::factory()->create();
    $unverifiedParent = User::factory()->create();

    ParentStudentLink::create([
        'parent_user_id' => $parent->id,
        'student_user_id' => $student->id,
        'relationship' => 'parent',
        'verified_at' => now(),
        'status' => ParentStudentLink::STATUS_APPROVED,
    ]);
    ParentStudentLink::create([
        'parent_user_id' => $unverifiedParent->id,
        'student_user_id' => $student->id,
        'relationship' => 'parent',
        'status' => ParentStudentLink::STATUS_PENDING,
    ]);

    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
    ]);
    $quiz = Quiz::factory()->withQuestions(1)->create([
        'curriculum_unit_id' => $unit->id,
        'title' => 'Unit quiz',
    ]);
    $attempt = QuizAttempt::factory()->submitted(80)->create([
        'user_id' => $student->id,
        'quiz_id' => $quiz->id,
        'earned_points' => 8,
        'total_points' => 10,
    ]);

    Notification::fake();

    $feedback = 'Review question two before the next attempt.';

    $this->actingAs($teacher)
        ->post(route('teacher.gradebook.quiz-feedback', $attempt->id), [
            'teacher_feedback' => $feedback,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($attempt->fresh()->teacher_feedback)->toBe($feedback);

    Notification::assertSentTo($student, GenericDatabaseNotification::class);
    Notification::assertSentTo($parent, GenericDatabaseNotification::class);
    Notification::assertNotSentTo($unverifiedParent, GenericDatabaseNotification::class);

    $this->actingAs($teacher)
        ->post(route('teacher.gradebook.quiz-feedback', $attempt->id), [
            'teacher_feedback' => $feedback,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentToTimes($student, GenericDatabaseNotification::class, 1);
    Notification::assertSentToTimes($parent, GenericDatabaseNotification::class, 1);

    $this->actingAs($teacher)
        ->post(route('teacher.gradebook.quiz-feedback', $attempt->id), [
            'teacher_feedback' => '   ',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($attempt->fresh()->teacher_feedback)->toBeNull();
    Notification::assertSentToTimes($student, GenericDatabaseNotification::class, 1);
    Notification::assertSentToTimes($parent, GenericDatabaseNotification::class, 1);
});
