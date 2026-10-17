<?php

declare(strict_types=1);

use App\Application\Quiz\Services\StudentAssessmentService;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        'teacher_feedback' => 'راجع السؤال الثاني.',
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
            ->where('items', fn ($items): bool => collect($items)->firstWhere('id', $available->id)['best_attempt']['teacher_feedback'] === 'راجع السؤال الثاني.')
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

it('lists both electronic quizzes and paper essay exams with inferred scopes', function (): void {
    Role::findOrCreate('student', 'web');
    $student = User::factory()->create();
    $student->assignRole('student');
    $grade = GradeLevel::query()->firstOrFail();
    $subject = Subject::query()->firstOrFail();
    $assignment = TeachingAssignment::factory()->create([
        'grade_level_id' => $grade->id,
        'subject_id' => $subject->id,
    ]);
    $unit1 = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'title' => 'الوحدة الأولى: الجبر',
        'is_published' => true,
    ]);
    $unit2 = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'title' => 'المراجعة الشاملة ونهاية الفصل',
        'is_published' => true,
    ]);
    $group1 = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $assignment->id,
    ]);
    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_assignment_id' => $assignment->id,
        'teaching_group_id' => $group1->id,
    ]);

    // 1. Electronic Unit Quiz
    Quiz::factory()->withQuestions(2)->create([
        'curriculum_unit_id' => $unit1->id,
        'title' => 'اختبار الجبر',
    ]);

    // 2. Paper Essay Exam on Unit 1
    Worksheet::create([
        'curriculum_unit_id' => $unit1->id,
        'lesson_id' => null,
        'title' => 'اختبار مقالي ورقي على الجبر',
        'file_path' => 'private://exams/algebra-exam.pdf',
        'type' => Worksheet::TYPE_PAPER_EXAM,
        'requires_submission' => true,
        'max_score' => 50,
        'due_date' => now()->addDays(5),
    ]);

    // 3. Comprehensive Mock Exam on Unit 2
    Worksheet::create([
        'curriculum_unit_id' => $unit2->id,
        'lesson_id' => null,
        'title' => 'الامتحان التجريبي الشامل للمنهج',
        'file_path' => 'private://exams/comprehensive-exam.pdf',
        'type' => Worksheet::TYPE_PAPER_EXAM,
        'requires_submission' => true,
        'max_score' => 100,
        'due_date' => now()->addDays(10),
    ]);

    $this->actingAs($student)
        ->get(route('student.assessments'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Student/Assessments')
            ->where('summary.total', 3)
            ->where('summary.electronic_count', 1)
            ->where('summary.paper_count', 2)
            ->where('summary.unit_count', 2)
            ->where('summary.comprehensive_count', 1)
            ->has('items', 3)
            ->where('items', fn ($items) => collect($items)->contains(fn ($i) => $i['mode'] === 'paper' && $i['scope'] === 'comprehensive'))
            ->where('items', fn ($items) => collect($items)->contains(fn ($i) => $i['mode'] === 'electronic' && $i['scope'] === 'unit')));
});

it('allows a student to submit a paper exam and records their submission', function (): void {
    Storage::fake('local');
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
    $group2 = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $assignment->id,
    ]);
    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_assignment_id' => $assignment->id,
        'teaching_group_id' => $group2->id,
    ]);

    $paperExam = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'lesson_id' => null,
        'title' => 'اختبار مقالي شامل',
        'file_path' => 'private://exams/test.pdf',
        'type' => Worksheet::TYPE_PAPER_EXAM,
        'requires_submission' => true,
        'max_score' => 40,
    ]);

    $file = UploadedFile::fake()->create('my-solution.pdf', 500, 'application/pdf');

    $response = $this->actingAs($student)
        ->post(route('student.assessments.submit-paper', $paperExam->id), [
            'submitted_file' => $file,
        ]);

    $response->assertRedirect()->assertSessionHas('success');

    $submission = WorksheetSubmission::where('worksheet_id', $paperExam->id)
        ->where('student_id', $student->id)
        ->first();

    expect($submission)->not->toBeNull()
        ->and($submission->submitted_at)->not->toBeNull();
});

it('forbids submitting paper exams for non-subscribed assignments', function (): void {
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

    $paperExam = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'lesson_id' => null,
        'title' => 'اختبار مقالي بدون اشتراك',
        'file_path' => 'private://exams/test.pdf',
        'type' => Worksheet::TYPE_PAPER_EXAM,
        'requires_submission' => true,
    ]);

    $file = UploadedFile::fake()->create('answer.pdf', 200, 'application/pdf');

    $response = $this->actingAs($student)
        ->post(route('student.assessments.submit-paper', $paperExam->id), [
            'submitted_file' => $file,
        ]);

    $response->assertForbidden();
});

