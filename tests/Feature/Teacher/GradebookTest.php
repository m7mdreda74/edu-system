<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('combines submitted homework and the latest submitted quiz attempt without zeroing missing work', function (): void {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');
    $student = User::factory()->create();
    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
        'is_published' => true,
    ]);
    $submitted = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'file_path' => 'private://worksheets/algebra.pdf',
        'title' => 'واجب الجبر',
        'type' => Worksheet::TYPE_HOMEWORK,
        'requires_submission' => true,
        'max_score' => 20,
    ]);
    Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'file_path' => 'private://worksheets/missing.pdf',
        'title' => 'واجب غير مسلّم',
        'type' => Worksheet::TYPE_HOMEWORK,
        'requires_submission' => true,
        'max_score' => 10,
    ]);
    WorksheetSubmission::create([
        'worksheet_id' => $submitted->id,
        'student_id' => $student->id,
        'submitted_file_path' => 'private://submissions/algebra.pdf',
        'score' => 16,
        'submitted_at' => now()->subDay(),
        'graded_at' => now(),
        'teacher_feedback' => 'مراجعة جيدة',
    ]);
    $quiz = Quiz::create([
        'curriculum_unit_id' => $unit->id,
        'title' => 'اختبار الوحدة الأولى',
        'passing_score' => 70,
        'is_active' => true,
    ]);
    QuizAttempt::factory()->submitted(60)->create([
        'quiz_id' => $quiz->id,
        'user_id' => $student->id,
        'earned_points' => 6,
        'total_points' => 10,
        'submitted_at' => now()->subDays(2),
    ]);
    QuizAttempt::factory()->submitted(80)->create([
        'quiz_id' => $quiz->id,
        'user_id' => $student->id,
        'earned_points' => 8,
        'total_points' => 10,
        'submitted_at' => now()->subDay(),
    ]);

    $this->actingAs($teacher)
        ->get(route('teacher.gradebook'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/Gradebook')
            ->where('summary.assessments', 2)
            ->where('summary.students', 1)
            ->where('summary.graded', 2)
            ->where('summary.needs_grading', 0)
            ->where('summary.earned_points', 24)
            ->where('summary.maximum_points', 30)
            ->where('rows.data', fn ($rows): bool => collect($rows)->contains(
                fn (array $row): bool => $row['title'] === 'اختبار الوحدة الأولى'
                    && $row['earned'] === 8
                    && $row['percentage'] === 80,
            ))
            ->has('rows.data', 2));
});

it('shows submitted but ungraded work and rejects another teachers assignment filter', function (): void {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');
    $otherTeacher = User::factory()->create(['email_verified_at' => now()]);
    $otherTeacher->assignRole('teacher');
    $student = User::factory()->create();
    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
    ]);
    $worksheet = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'file_path' => 'private://worksheets/review.pdf',
        'title' => 'يحتاج مراجعة',
        'type' => Worksheet::TYPE_HOMEWORK,
        'requires_submission' => true,
        'max_score' => 15,
    ]);
    WorksheetSubmission::create([
        'worksheet_id' => $worksheet->id,
        'student_id' => $student->id,
        'submitted_file_path' => 'private://submissions/review.pdf',
        'submitted_at' => now(),
    ]);
    $otherAssignment = TeachingAssignment::factory()->create([
        'teacher_id' => $otherTeacher->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);

    $this->actingAs($teacher)
        ->get(route('teacher.gradebook'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.needs_grading', 1)
            ->where('rows.data.0.status', 'needs_grading'));

    $this->actingAs($teacher)
        ->get(route('teacher.gradebook', ['assignment' => $otherAssignment->id]))
        ->assertNotFound();

    $this->actingAs($teacher)
        ->get(route('teacher.gradebook.export', ['assignment' => $otherAssignment->id]))
        ->assertNotFound();
});

it('paginates the gradebook rows and keeps summary totals across pages', function (): void {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');
    $student = User::factory()->create();
    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
    ]);

    foreach ([3, 2, 1] as $daysAgo) {
        $worksheet = Worksheet::create([
            'curriculum_unit_id' => $unit->id,
            'file_path' => "private://worksheets/page-{$daysAgo}.pdf",
            'title' => "Submission {$daysAgo}",
            'type' => Worksheet::TYPE_HOMEWORK,
            'requires_submission' => true,
            'max_score' => 10,
        ]);
        WorksheetSubmission::create([
            'worksheet_id' => $worksheet->id,
            'student_id' => $student->id,
            'submitted_file_path' => "private://submissions/page-{$daysAgo}.pdf",
            'score' => 8,
            'submitted_at' => now()->subDays($daysAgo),
            'graded_at' => now(),
        ]);
    }

    $this->actingAs($teacher)
        ->get(route('teacher.gradebook', ['per_page' => 2]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.assessments', 3)
            ->where('rows.total', 3)
            ->where('rows.per_page', 2)
            ->where('rows.current_page', 1)
            ->has('rows.data', 2));

    $this->actingAs($teacher)
        ->get(route('teacher.gradebook', ['per_page' => 2, 'page' => 2]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.assessments', 3)
            ->where('rows.current_page', 2)
            ->where('rows.data.0.title', 'Submission 3')
            ->has('rows.data', 1));
});

it('exports only the teacher scoped gradebook and neutralizes spreadsheet formulas', function (): void {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');
    $student = User::factory()->create([
        'name' => '=SUM(A1:A2)',
        'email' => 'formula@example.test',
    ]);
    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
    ]);
    $worksheet = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'file_path' => 'private://worksheets/export.pdf',
        'title' => 'Export homework',
        'type' => Worksheet::TYPE_HOMEWORK,
        'requires_submission' => true,
        'max_score' => 10,
    ]);
    WorksheetSubmission::create([
        'worksheet_id' => $worksheet->id,
        'student_id' => $student->id,
        'submitted_file_path' => 'private://submissions/export.pdf',
        'score' => 8,
        'submitted_at' => now(),
        'graded_at' => now(),
    ]);

    $response = $this->actingAs($teacher)
        ->get(route('teacher.gradebook.export', ['assignment' => $assignment->id]));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('cache-control', 'no-store, private');

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain("\xEF\xBB\xBFStudent,Email,Assessment,Type,Subject,Earned,Maximum,Percentage,Status,Feedback,\"Assessed At\"")
        ->toContain("'=SUM(A1:A2)")
        ->toContain('formula@example.test')
        ->toContain('Export homework')
        ->toContain(',8,10,80,graded,');
});

it('lets only the owning teacher save feedback on a submitted quiz attempt', function (): void {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');
    $otherTeacher = User::factory()->create(['email_verified_at' => now()]);
    $otherTeacher->assignRole('teacher');
    $student = User::factory()->create();
    $parent = User::factory()->create();
    ParentStudentLink::create([
        'parent_user_id' => $parent->id,
        'student_user_id' => $student->id,
        'relationship' => 'parent',
        'verified_at' => now(),
        'status' => ParentStudentLink::STATUS_APPROVED,
    ]);
    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
    ]);
    $quiz = Quiz::factory()->withQuestions(1)->create(['curriculum_unit_id' => $unit->id]);
    $attempt = QuizAttempt::factory()->submitted(80)->create([
        'user_id' => $student->id,
        'quiz_id' => $quiz->id,
        'earned_points' => 8,
        'total_points' => 10,
    ]);

    $this->actingAs($teacher)
        ->post(route('teacher.gradebook.quiz-feedback', $attempt->id), [
            'teacher_feedback' => 'راجع السؤال الثاني قبل المحاولة القادمة.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($attempt->fresh()->teacher_feedback)->toBe('راجع السؤال الثاني قبل المحاولة القادمة.');

    $this->actingAs($otherTeacher)
        ->post(route('teacher.gradebook.quiz-feedback', $attempt->id), [
            'teacher_feedback' => 'لا يجب أن تظهر.',
        ])
        ->assertNotFound();
});
