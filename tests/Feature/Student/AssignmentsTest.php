<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('lists only active-subscription homework with real submission statuses', function (): void {
    Role::findOrCreate('student', 'web');
    $student = User::factory()->create(['email_verified_at' => now()]);
    $student->assignRole('student');
    $assignment = TeachingAssignment::factory()->create([
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $group = TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
        'is_published' => true,
    ]);
    $lesson = GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);
    $homework = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'lesson_id' => $lesson->id,
        'title' => 'واجب الجبر',
        'file_path' => 'private://worksheets/algebra.pdf',
        'type' => Worksheet::TYPE_HOMEWORK,
        'requires_submission' => true,
        'due_date' => today()->addDay(),
        'max_score' => 20,
    ]);
    LessonProgress::create([
        'student_id' => $student->id,
        'lesson_id' => $lesson->id,
        'watched_seconds' => 90,
        'is_completed' => false,
    ]);
    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_group_id' => $group->id,
        'teaching_assignment_id' => $assignment->id,
    ]);

    $this->actingAs($student)
        ->get(route('student.assignments'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Student/Assignments')
            ->where('summary.in_progress', 1)
            ->where('assignments.0.id', $homework->id)
            ->where('assignments.0.status', 'in_progress')
            ->where('assignments.0.group_id', $group->id)
            ->where('assignments.0.lesson_id', $lesson->id)
            ->where('assignments.0.term_id', $unit->academic_term_id));

    $this->actingAs($student)
        ->get(route('student.learn', [
            'groupId' => $group->id,
            'worksheet' => $homework->id,
            'term' => $unit->academic_term_id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Student/Learn')
            ->where('initialLessonId', $lesson->id));

    WorksheetSubmission::create([
        'worksheet_id' => $homework->id,
        'student_id' => $student->id,
        'submitted_file_path' => 'private://submissions/algebra.pdf',
        'submitted_at' => now(),
    ]);

    $this->actingAs($student)
        ->get(route('student.assignments'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.submitted', 1)
            ->where('assignments.0.status', 'submitted'));
});

it('does not expose homework belonging to another assignment', function (): void {
    Role::findOrCreate('student', 'web');
    $student = User::factory()->create(['email_verified_at' => now()]);
    $student->assignRole('student');
    $assignment = TeachingAssignment::factory()->create([
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
        'is_published' => true,
    ]);
    $lesson = GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);
    Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'lesson_id' => $lesson->id,
        'title' => 'غير مصرح',
        'file_path' => 'private://worksheets/other.pdf',
        'type' => Worksheet::TYPE_HOMEWORK,
        'requires_submission' => true,
    ]);

    $this->actingAs($student)
        ->get(route('student.assignments'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.total', 0)
            ->has('assignments', 0));
});

it('sends missing homework alert notification and marks assignment as late when deadline has passed', function (): void {
    Role::findOrCreate('student', 'web');
    $student = User::factory()->create(['email_verified_at' => now()]);
    $student->assignRole('student');
    $assignment = TeachingAssignment::factory()->create([
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $group = TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
        'is_published' => true,
    ]);
    $lesson = GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);
    $homework = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'lesson_id' => $lesson->id,
        'title' => 'واجب متأخر',
        'file_path' => 'private://worksheets/late_hw.pdf',
        'type' => Worksheet::TYPE_HOMEWORK,
        'requires_submission' => true,
        'due_date' => today()->subDays(2),
        'max_score' => 20,
    ]);

    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_group_id' => $group->id,
        'teaching_assignment_id' => $assignment->id,
    ]);

    $this->actingAs($student)
        ->get(route('student.assignments'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Student/Assignments')
            ->where('summary.late', 1)
            ->where('assignments.0.id', $homework->id)
            ->where('assignments.0.status', 'late')
            ->where('assignments.0.is_late', true)
            ->where('assignments.0.submitted_late', false));

    // Confirm that notification was created for student
    $notification = $student->notifications()->first();
    expect($notification)->not->toBeNull();
    expect($notification->data['title'])->toContain('تأخرت في تسليم الواجب');
    expect($notification->data['alert_type'])->toBe('missing_assignment');
});

it('marks submission as late and shows late notice even if submitted after deadline', function (): void {
    Role::findOrCreate('student', 'web');
    $student = User::factory()->create(['email_verified_at' => now()]);
    $student->assignRole('student');
    $assignment = TeachingAssignment::factory()->create([
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $group = TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
        'is_published' => true,
    ]);
    $lesson = GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);
    $homework = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'lesson_id' => $lesson->id,
        'title' => 'واجب الفيزياء المتأخر',
        'file_path' => 'private://worksheets/physics.pdf',
        'type' => Worksheet::TYPE_HOMEWORK,
        'requires_submission' => true,
        'due_date' => today()->subDays(3),
        'max_score' => 10,
    ]);

    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_group_id' => $group->id,
        'teaching_assignment_id' => $assignment->id,
    ]);

    // Submit homework after deadline
    $submission = WorksheetSubmission::create([
        'worksheet_id' => $homework->id,
        'student_id' => $student->id,
        'submitted_file_path' => 'private://submissions/physics_solved.pdf',
        'submitted_at' => now(),
        'is_late' => true,
    ]);

    expect($submission->isLate())->toBeTrue();

    $this->actingAs($student)
        ->get(route('student.assignments'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Student/Assignments')
            ->where('summary.late', 1)
            ->where('assignments.0.id', $homework->id)
            ->where('assignments.0.is_late', true)
            ->where('assignments.0.submitted_late', true)
            ->where('assignments.0.late_notice', 'تم التسليم بعد انتهاء الموعد المحدد (تأخر في التسليم)')
            ->where('assignments.0.submission.is_late', true));
});

