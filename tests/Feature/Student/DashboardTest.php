<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('selects the first incomplete authorized lesson for continue learning', function (): void {
    Role::findOrCreate('student', 'web');

    $student = User::factory()->create(['email_verified_at' => now()]);
    $student->assignRole('student');
    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');
    $subject = Subject::factory()->create();
    $term = AcademicTerm::currentOrNext() ?? AcademicTerm::firstOrFail();
    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $group = TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => $term->id,
        'order' => 1,
    ]);
    $first = GroupMaterial::factory()->create([
        'curriculum_unit_id' => $unit->id,
        'order' => 1,
        'title' => 'الدرس المكتمل',
    ]);
    $second = GroupMaterial::factory()->create([
        'curriculum_unit_id' => $unit->id,
        'order' => 2,
        'title' => 'الدرس التالي',
    ]);
    LessonProgress::create([
        'student_id' => $student->id,
        'lesson_id' => $first->id,
        'watched_seconds' => 600,
        'is_completed' => true,
    ]);
    LessonProgress::create([
        'student_id' => $student->id,
        'lesson_id' => $second->id,
        'watched_seconds' => 120,
        'is_completed' => false,
    ]);
    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_group_id' => $group->id,
        'teaching_assignment_id' => $assignment->id,
    ]);

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('continueLearning.lesson_id', $second->id)
            ->where('continueLearning.group_id', $group->id)
            ->where('continueLearning.watched_seconds', 120));
});

it('does not expose continue learning for an expired subscription', function (): void {
    Role::findOrCreate('student', 'web');
    $student = User::factory()->create(['email_verified_at' => now()]);
    $student->assignRole('student');
    $assignment = TeachingAssignment::factory()->create([
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $assignment->id,
    ]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()?->id,
    ]);
    GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);
    Subscription::factory()->expired()->create([
        'student_id' => $student->id,
        'teaching_group_id' => $group->id,
        'teaching_assignment_id' => $assignment->id,
    ]);

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('continueLearning', null));
});

it('keeps gamification disabled by default and computes scoped non-repeat achievements when enabled', function (): void {
    Role::findOrCreate('student', 'web');
    $student = User::factory()->create(['email_verified_at' => now()]);
    $student->assignRole('student');
    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');
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
    LessonProgress::create([
        'student_id' => $student->id,
        'lesson_id' => $lesson->id,
        'watched_seconds' => 900,
        'is_completed' => true,
    ]);

    $quiz = Quiz::factory()->withQuestions(1)->create(['curriculum_unit_id' => $unit->id]);
    QuizAttempt::factory()->submitted(80)->create(['user_id' => $student->id, 'quiz_id' => $quiz->id]);
    QuizAttempt::factory()->submitted(90)->create(['user_id' => $student->id, 'quiz_id' => $quiz->id]);

    $worksheet = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'lesson_id' => $lesson->id,
        'title' => 'واجب الإنجاز',
        'file_path' => '/storage/achievement-homework.pdf',
        'type' => Worksheet::TYPE_HOMEWORK,
        'max_score' => 10,
    ]);
    WorksheetSubmission::create([
        'worksheet_id' => $worksheet->id,
        'student_id' => $student->id,
        'submitted_file_path' => '/storage/achievement-submission.pdf',
        'submitted_at' => now(),
        'graded_at' => now(),
        'score' => 10,
    ]);

    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_group_id' => $group->id,
        'teaching_assignment_id' => $assignment->id,
    ]);

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('gamification', null));

    Config::set('learning.gamification_enabled', true);

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('gamification.points', 30)
            ->where('gamification.level.key', 'starter')
            ->where('gamification.breakdown.0.count', 1)
            ->where('gamification.breakdown.0.points', 10)
            ->where('gamification.breakdown.1.count', 1)
            ->where('gamification.breakdown.1.points', 15)
            ->where('gamification.breakdown.2.count', 1)
            ->where('gamification.breakdown.2.points', 5)
            ->where('gamification.milestones.0.achieved', true)
            ->where('gamification.next_milestone.key', 'active_learner')
            ->where('gamification.privacy.public_leaderboard', false)
            ->has('gamification.badges', 1));

    Config::set('learning.gamification_enabled', false);
});
