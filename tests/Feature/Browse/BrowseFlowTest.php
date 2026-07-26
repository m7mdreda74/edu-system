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
    $this->grade   = GradeLevel::where('key', 'grade_12_science')->firstOrFail();
    $this->subject = Subject::where('name', 'الرياضيات')->firstOrFail();

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

it('shows every stage of the Qatari system on the home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('Public/Home');

            $stages = collect($page->toArray()['props']['grades'])->pluck('stage')->unique();

            expect($stages)->toContain('primary', 'preparatory', 'secondary');
        });
});

it('splits grades eleven and twelve into the three Qatari tracks', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $tracked = collect($page->toArray()['props']['grades'])
                ->whereNotNull('track')
                ->pluck('key');

            expect($tracked)->toContain(
                'grade_11_science',
                'grade_11_arts',
                'grade_11_technology',
                'grade_12_science',
                'grade_12_arts',
                'grade_12_technology',
            );
        });
});

it('lists the whole curriculum for a grade, teachers or not', function () {
    $this->get(route('grades.show', ['key' => 'grade_12_science']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $subjects = collect($page->toArray()['props']['subjects']);

            // The science track carries physics whether or not it is staffed.
            expect($subjects->pluck('name'))->toContain('الفيزياء')
                // And the test's own subject, which has a teacher.
                ->toContain('الرياضيات');

            expect($subjects->firstWhere('name', 'الرياضيات')['teachers_count'])->toBe(1);
        });
});

it('marks curriculum subjects with no teacher as unstaffed', function () {
    $this->get(route('grades.show', ['key' => 'grade_12_arts']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $subjects = collect($page->toArray()['props']['subjects']);

            expect($subjects)->not->toBeEmpty()
                ->and($subjects->every(fn ($s) => $s['teachers_count'] === 0))->toBeTrue();
        });
});

it('offers the sibling track when viewing a tracked grade', function () {
    $this->get(route('grades.show', ['key' => 'grade_12_science']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('grade.track', 'science')
            ->where('grade.track_label', 'المسار العلمي')
            // Two siblings now: arts and technology.
            ->has('siblingTracks', 2));
});

it('lists the teachers who teach a subject with their intro video', function () {
    $this->get(route('subjects.teachers', ['gradeKey' => 'grade_12_science', 'subject' => $this->subject->id]))
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
