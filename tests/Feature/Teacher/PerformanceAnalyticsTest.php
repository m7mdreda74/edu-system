<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
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

it('shows teacher-scoped analytics and explainable attention signals', function (): void {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');
    $student = User::factory()->create(['name' => 'Student Active']);
    $studentWithGaps = User::factory()->create(['name' => 'Student Needs Follow Up']);
    $otherTeacher = User::factory()->create(['email_verified_at' => now()]);
    $otherTeacher->assignRole('teacher');

    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $group = TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
        'is_published' => true,
    ]);
    $lesson = GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);
    $progress = LessonProgress::create([
        'student_id' => $student->id,
        'lesson_id' => $lesson->id,
        'watched_seconds' => 1_800,
        'is_completed' => true,
    ]);
    $progress->forceFill(['updated_at' => now()->subDays(2)])->saveQuietly();

    $homework = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'lesson_id' => $lesson->id,
        'title' => 'واجب التحليل',
        'file_path' => 'private://worksheets/analytics.pdf',
        'type' => Worksheet::TYPE_HOMEWORK,
        'requires_submission' => true,
        'due_date' => today()->subDay(),
        'max_score' => 20,
    ]);
    WorksheetSubmission::create([
        'worksheet_id' => $homework->id,
        'student_id' => $student->id,
        'submitted_file_path' => 'private://submissions/analytics.pdf',
        'score' => 18,
        'submitted_at' => now()->subDay(),
        'graded_at' => now(),
    ]);

    $quiz = Quiz::factory()->withQuestions(1)->create([
        'curriculum_unit_id' => $unit->id,
        'is_active' => true,
    ]);
    QuizAttempt::factory()->submitted(80)->create([
        'user_id' => $student->id,
        'quiz_id' => $quiz->id,
        'earned_points' => 8,
        'total_points' => 10,
        'submitted_at' => now()->subDay(),
    ]);
    QuizAttempt::factory()->submitted(40)->create([
        'user_id' => $studentWithGaps->id,
        'quiz_id' => $quiz->id,
        'earned_points' => 4,
        'total_points' => 10,
        'submitted_at' => now()->subDay(),
    ]);

    foreach ([$student, $studentWithGaps] as $rosterStudent) {
        Subscription::factory()->active()->create([
            'student_id' => $rosterStudent->id,
            'teaching_assignment_id' => $assignment->id,
            'teaching_group_id' => $group->id,
        ]);
    }

    $session = LiveSession::create([
        'teacher_id' => $teacher->id,
        'teaching_group_id' => $group->id,
        'title' => 'جلسة التحليل',
        'scheduled_at' => now()->subDay(),
        'started_at' => now()->subDay()->addMinutes(2),
        'ended_at' => now()->subDay()->addHour(),
        'status' => LiveSession::STATUS_ENDED,
    ]);
    $session->attendees()->create([
        'user_id' => $student->id,
        'joined_at' => now()->subDay()->addMinutes(5),
        'left_at' => now()->subDay()->addMinutes(55),
    ]);
    $secondSession = LiveSession::create([
        'teacher_id' => $teacher->id,
        'teaching_group_id' => $group->id,
        'title' => 'جلسة التحليل الثانية',
        'scheduled_at' => now()->subDays(2),
        'started_at' => now()->subDays(2)->addMinutes(2),
        'ended_at' => now()->subDays(2)->addHour(),
        'status' => LiveSession::STATUS_ENDED,
    ]);
    $secondSession->attendees()->create([
        'user_id' => $student->id,
        'joined_at' => now()->subDays(2)->addMinutes(5),
        'left_at' => now()->subDays(2)->addMinutes(55),
    ]);

    $otherAssignment = TeachingAssignment::factory()->create([
        'teacher_id' => $otherTeacher->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);

    $this->actingAs($teacher)
        ->get(route('teacher.analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/PerformanceAnalytics')
            ->where('summary.students', 2)
            ->where('summary.lessons_completed', 1)
            ->where('summary.study_minutes', 30)
            ->where('summary.assignments_submitted', 1)
            ->where('summary.missing_assignments', 1)
            ->where('summary.quiz_attempts', 2)
            ->where('summary.average_quiz_score', 60)
            ->where('summary.attendance_rate', 50)
            ->where('summary.students_requiring_attention', 1)
            ->where('rows.0.id', $studentWithGaps->id)
            ->where('rows.0.requires_attention', true)
            ->where('rows.0.attention_reasons.0.code', 'missing_assignments')
            ->where('rows.0.attention_reasons.1.code', 'low_attendance')
            ->where('trend', fn ($trend): bool => count($trend) === 5)
            ->where('quiz_history', fn ($history): bool => count($history) === 2));

    $this->actingAs($teacher)
        ->get(route('teacher.analytics', ['student' => $student->id]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.students', 1)
            ->where('rows.0.id', $student->id)
            ->where('summary.missing_assignments', 0));

    $this->actingAs($teacher)
        ->get(route('teacher.analytics', ['assignment' => $otherAssignment->id]))
        ->assertNotFound();
});
