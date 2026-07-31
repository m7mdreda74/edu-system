<?php

declare(strict_types=1);

use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// Binds $this inside every Pest closure in this file to Tests\TestCase.
uses(TestCase::class, RefreshDatabase::class);

// ─── A subject has many teachers; a teacher has one subject ─────────────────

beforeEach(function () {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->grade = GradeLevel::where('key', 'grade_12_science')->firstOrFail();
    $this->maths = Subject::where('name', 'الرياضيات')->firstOrFail();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    // Two teachers on the same subject — the case the browse flow exists for.
    $this->teacherA = User::factory()->create(['name' => 'أ. أحمد', 'is_active' => true, 'subject_id' => $this->maths->id]);
    $this->teacherA->assignRole('teacher');

    $this->teacherB = User::factory()->create(['name' => 'أ. سالم', 'is_active' => true, 'subject_id' => $this->maths->id]);
    $this->teacherB->assignRole('teacher');

    $this->groups = collect([$this->teacherA, $this->teacherB])->map(function (User $teacher) {
        $assignment = TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $this->maths->id,
            'grade_level_id' => $this->grade->id,
        ]);

        return TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id]);
    });

    $this->student = User::factory()->create([
        'grade_level' => 'grade_12_science',
        'email_verified_at' => now(),
    ]);
    $this->student->assignRole('student');

    $this->service = app(SubscriptionService::class);
});

it('shows the student every subject on their own grade', function () {
    $this->actingAs($this->student)
        ->get(route('student.my-grade'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('Student/MyGrade')->where('grade.key', 'grade_12_science');

            $names = collect($page->toArray()['props']['subjects'])->pluck('name');

            expect($names)->toContain('الرياضيات', 'الفيزياء', 'اللغة العربية');
        });
});

it('lists both teachers of a subject', function () {
    $this->actingAs($this->student)
        ->get(route('student.my-grade'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $maths = collect($page->toArray()['props']['subjects'])->firstWhere('name', 'الرياضيات');

            expect($maths['teachers'])->toHaveCount(2);
        });
});

it('marks a subject as unsubscribed until the student joins one of its teachers', function () {
    $this->actingAs($this->student)
        ->get(route('student.my-grade'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $maths = collect($page->toArray()['props']['subjects'])->firstWhere('name', 'الرياضيات');

            expect($maths['is_subscribed'])->toBeFalse()
                ->and($maths['subscribed_with'])->toBeNull()
                ->and(collect($maths['teachers'])->every(fn ($t) => $t['is_subscribed'] === false))->toBeTrue();
        });
});

it('names the teacher once the student subscribes, and only that teacher', function () {
    $this->service->activate($this->service->openForGroup($this->student, $this->groups->first()));

    $this->actingAs($this->student)
        ->get(route('student.my-grade'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $maths = collect($page->toArray()['props']['subjects'])->firstWhere('name', 'الرياضيات');

            expect($maths['is_subscribed'])->toBeTrue()
                ->and($maths['subscribed_with'])->toBe('أ. أحمد');

            $teachers = collect($maths['teachers']);

            expect($teachers->firstWhere('name', 'أ. أحمد')['is_subscribed'])->toBeTrue()
                ->and($teachers->firstWhere('name', 'أ. سالم')['is_subscribed'])->toBeFalse();
        });
});

it('leaves the other subjects unsubscribed', function () {
    $this->service->activate($this->service->openForGroup($this->student, $this->groups->first()));

    $this->actingAs($this->student)
        ->get(route('student.my-grade'))
        ->assertInertia(function ($page) {
            $physics = collect($page->toArray()['props']['subjects'])->firstWhere('name', 'الفيزياء');

            expect($physics['is_subscribed'])->toBeFalse();
        });
});

it('asks a student with no grade to set one', function () {
    $this->student->update(['grade_level' => null]);

    $this->actingAs($this->student)
        ->get(route('student.my-grade'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('grade', null));
});

it('carries the status onto the public subject page too', function () {
    $this->service->activate($this->service->openForGroup($this->student, $this->groups->first()));

    $this->actingAs($this->student)
        ->get(route('subjects.teachers', ['gradeKey' => 'grade_12_science', 'subject' => $this->maths->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('subscribedTeacherId', $this->teacherA->id)
            ->where('isStudent', true));
});

// ─── Only administration can assign teachers ───────────────────────────────

it('keeps teaching assignments away from teachers', function () {
    $this->actingAs($this->teacherA)
        ->post(route('admin.teaching-assignments.store'), [
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $this->maths->id,
            'grade_level_id' => GradeLevel::where('key', 'grade_11_science')->value('id'),
        ])
        ->assertForbidden();

    expect(app('router')->has('teacher.teaching-schedule.assignments.store'))->toBeFalse();
});

it('stops the admin assigning a teacher to a second subject', function () {
    $physics = Subject::where('name', 'الفيزياء')->firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('admin.teaching-assignments.store'), [
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $physics->id,
            'grade_level_id' => GradeLevel::where('key', 'grade_11_science')->value('id'),
        ])
        ->assertSessionHasErrors('subject_id');

    expect(TeachingAssignment::where('teacher_id', $this->teacherA->id)
        ->distinct('subject_id')->count('subject_id'))->toBe(1);
});

it('lets the admin add another grade of the teacher subject', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.teaching-assignments.store'), [
            'teacher_id' => $this->teacherA->id,
            'subject_id' => $this->maths->id,
            'grade_level_id' => GradeLevel::where('key', 'grade_11_science')->value('id'),
        ])
        ->assertSessionHasNoErrors();

    expect(TeachingAssignment::where('teacher_id', $this->teacherA->id)->count())->toBe(2);
});

it('sets a new teacher specialty from the first admin assignment', function () {
    $fresh = User::factory()->create(['is_active' => true, 'subject_id' => null]);
    $fresh->assignRole('teacher');

    $this->actingAs($this->admin)
        ->post(route('admin.teaching-assignments.store'), [
            'teacher_id' => $fresh->id,
            'subject_id' => $this->maths->id,
            'grade_level_id' => $this->grade->id,
        ]);

    expect($fresh->fresh()->subject_id)->toBe($this->maths->id);
});
