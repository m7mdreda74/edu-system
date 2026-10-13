<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
