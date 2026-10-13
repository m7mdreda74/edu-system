<?php

declare(strict_types=1);

use App\Application\Quiz\Services\StudentAssessmentService;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('lists only published active-subscription quizzes with real attempt state', function (): void {
    Role::findOrCreate('student', 'web');
    $student = User::factory()->create();
    $student->assignRole('student');
    $grade = GradeLevel::query()->firstOrFail();
    $subject = Subject::query()->firstOrFail();
    $assignment = TeachingAssignment::factory()->create([
        'grade_level_id' => $grade->id,
        'subject_id' => $subject->id,
    ]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'is_published' => true,
    ]);
    $group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $assignment->id,
    ]);
    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_assignment_id' => $assignment->id,
        'teaching_group_id' => $group->id,
    ]);

    $available = Quiz::factory()->withQuestions(2)->create([
        'curriculum_unit_id' => $unit->id,
        'title' => 'اختبار متاح',
    ]);
    Quiz::factory()->withQuestions(1)->create([
        'curriculum_unit_id' => $unit->id,
        'title' => 'اختبار مفتوح',
    ]);
    Quiz::factory()->withQuestions(1)->create([
        'curriculum_unit_id' => $unit->id,
        'title' => 'اختبار قادم',
        'available_from' => now()->addDay(),
    ]);
    QuizAttempt::factory()->submitted(80)->create([
        'user_id' => $student->id,
        'quiz_id' => $available->id,
        'earned_points' => 2,
        'total_points' => 2,
    ]);

    $unpublishedUnit = CurriculumUnit::factory()->unpublished()->create([
        'teaching_assignment_id' => $assignment->id,
    ]);
    Quiz::factory()->withQuestions(1)->create([
        'curriculum_unit_id' => $unpublishedUnit->id,
        'title' => 'اختبار غير منشور',
    ]);
    $otherAssignment = TeachingAssignment::factory()->create([
        'grade_level_id' => $grade->id,
        'subject_id' => $subject->id,
    ]);
    $otherUnit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $otherAssignment->id,
    ]);
    Quiz::factory()->withQuestions(1)->create([
        'curriculum_unit_id' => $otherUnit->id,
        'title' => 'اختبار غير مصرح',
    ]);

    $this->actingAs($student)
        ->get(route('student.assessments'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Student/Assessments')
            ->where('summary.total', 3)
            ->where('summary.available', 1)
            ->where('summary.completed', 1)
            ->where('summary.average_score', 80)
            ->where('items', fn ($items): bool => collect($items)->pluck('title')->sort()->values()->all() === ['اختبار قادم', 'اختبار متاح', 'اختبار مفتوح'])
            ->where('items', fn ($items): bool => collect($items)->firstWhere('id', $available->id)['best_attempt']['score'] === 80)
            ->where('items', fn ($items): bool => collect($items)->every(fn (array $item): bool => ! array_key_exists('questions', $item) && ! array_key_exists('options', $item) && ! array_key_exists('answer_key', $item)))
            ->missing('items.3'));
});

it('does not expose assessment data without an active subscription', function (): void {
    Role::findOrCreate('student', 'web');
    $student = User::factory()->create();
    $student->assignRole('student');
    $assignment = TeachingAssignment::factory()->create([
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
        'subject_id' => Subject::query()->firstOrFail()->id,
    ]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'is_published' => true,
    ]);
    Quiz::factory()->withQuestions(1)->create(['curriculum_unit_id' => $unit->id]);

    $response = $this->actingAs($student)->get(route('student.assessments'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Student/Assessments')
        ->where('summary.total', 0)
        ->has('items', 0));

    expect(app(StudentAssessmentService::class)->forStudent($student)['items'])->toBeEmpty();
});
