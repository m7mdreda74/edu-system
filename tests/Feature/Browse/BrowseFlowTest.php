<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Spatie\Permission\Models\Role;

// ─── Public browse flow: grade → subject → teachers → profile ────────────────

beforeEach(function () {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    // The grade_levels migration ships grade_10/11/12, so reuse rather than insert.
    $this->grade   = GradeLevel::where('key', 'grade_12')->firstOrFail();
    $this->subject = Subject::factory()->create(['name' => 'الرياضيات', 'is_active' => true]);

    $this->teacher = User::factory()->create([
        'name'             => 'أ. أحمد',
        'is_active'        => true,
        'headline'         => 'معلم رياضيات',
        'intro_video_url'  => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'years_experience' => 10,
    ]);
    $this->teacher->assignRole('teacher');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id'     => $this->teacher->id,
        'subject_id'     => $this->subject->id,
        'grade_level_id' => $this->grade->id,
        'is_active'      => true,
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'monthly_price'          => 45_000,
        'capacity'               => 10,
    ]);
});

it('shows active grades on the home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Home')
            ->has('grades', 1)
            ->where('grades.0.key', 'grade_12'));
});

it('opens the subjects of a grade', function () {
    $this->get(route('grades.show', ['key' => 'grade_12']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/GradeSubjects')
            ->has('subjects', 1)
            ->where('subjects.0.teachers_count', 1));
});

it('hides subjects that have no teacher assigned', function () {
    Subject::factory()->create(['name' => 'مادة بلا معلم', 'is_active' => true]);

    $this->get(route('grades.show', ['key' => 'grade_12']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('subjects', 1));
});

it('lists the teachers who teach a subject with their intro video', function () {
    $this->get(route('subjects.teachers', ['gradeKey' => 'grade_12', 'subject' => $this->subject->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/SubjectTeachers')
            ->has('teachers', 1)
            ->where('teachers.0.id', $this->teacher->id)
            ->where('teachers.0.intro_video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->where('teachers.0.cheapest_monthly', 45_000)
            ->where('teachers.0.has_free_seats', true));
});

it('shows a teacher profile with their groups and prices', function () {
    $this->get(route('teachers.show', $this->teacher->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/TeacherProfile')
            ->where('teacher.name', 'أ. أحمد')
            ->has('assignments', 1)
            ->has('assignments.0.groups', 1)
            ->where('assignments.0.groups.0.monthly_price', 45_000)
            ->where('assignments.0.groups.0.seats_left', 10));
});

it('does not show inactive teachers', function () {
    $this->teacher->update(['is_active' => false]);

    $this->get(route('teachers.show', $this->teacher->id))->assertNotFound();
});

it('has no route named after courses any more', function () {
    $names = collect(app('router')->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter();

    expect($names->filter(fn ($name) => str_contains($name, 'course')))->toBeEmpty();
});
