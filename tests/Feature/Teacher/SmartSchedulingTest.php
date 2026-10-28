<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupLesson;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->teacher = User::factory()->create(['email_verified_at' => now()]);
    $this->teacher->assignRole('teacher');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => Subject::query()->firstOrFail()->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
        'accepts_private' => true,
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'name' => 'المجموعة الأولى',
        'capacity' => 20,
    ]);
});

it('lets teacher create multiday schedules with duration and auto-calculated end time', function () {
    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.teaching-schedule.groups.schedules.store', $this->group->id), [
            'days' => [6, 2], // Saturday & Tuesday
            'start_time' => '19:00',
            'duration_minutes' => 60,
            'auto_schedule' => false,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teaching_group_schedules', [
        'teaching_group_id' => $this->group->id,
        'day_of_week' => 6,
        'start_time' => '19:00',
        'end_time' => '20:00',
        'duration_minutes' => 60,
    ]);

    $this->assertDatabaseHas('teaching_group_schedules', [
        'teaching_group_id' => $this->group->id,
        'day_of_week' => 2,
        'start_time' => '19:00',
        'end_time' => '20:00',
        'duration_minutes' => 60,
    ]);
});

it('automatically schedules pending group lessons when schedule is created', function () {
    TeachingGroupLesson::create([
        'teaching_group_id' => $this->group->id,
        'title' => 'الدرس الأول: الجبر',
        'position' => 1,
        'status' => 'pending',
    ]);
    TeachingGroupLesson::create([
        'teaching_group_id' => $this->group->id,
        'title' => 'الدرس الثاني: المعادلات',
        'position' => 2,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.teaching-schedule.groups.schedules.store', $this->group->id), [
            'days' => [6, 2],
            'start_time' => '19:00',
            'duration_minutes' => 60,
            'auto_schedule' => true,
        ]);

    $response->assertRedirect();

    $lessons = TeachingGroupLesson::where('teaching_group_id', $this->group->id)->get();
    expect($lessons)->toHaveCount(2);
    foreach ($lessons as $lesson) {
        expect($lesson->status)->toBe('scheduled');
        expect($lesson->live_session_id)->not->toBeNull();
    }
});

it('publishes a quick lesson with video material seamlessly', function () {
    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.curriculum.quick-lesson', $this->assignment->id), [
            'new_unit_title' => 'الوحدة الأولى',
            'title' => 'مقدمة في الجبر',
            'description' => 'شرح الدرس الأول',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('group_materials', [
        'title' => 'مقدمة في الجبر',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);
});

it('lets teacher create a 2-day weekly private group with capacity 1 and monthly price', function () {
    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.teaching-schedule.private-groups.store', $this->assignment->id), [
            'days' => [6, 2], // Saturday & Tuesday
            'start_time' => '16:00',
            'duration_minutes' => 60,
        ]);

    $response->assertRedirect();

    $privateGroup = TeachingGroup::where('teaching_assignment_id', $this->assignment->id)
        ->where('is_private', true)
        ->first();

    expect($privateGroup)->not->toBeNull();
    expect($privateGroup->capacity)->toBe(1);
    expect($privateGroup->isPrivate())->toBeTrue();
    expect($privateGroup->monthly_price)->toBe($this->assignment->private_monthly_price);

    $this->assertDatabaseHas('teaching_group_schedules', [
        'teaching_group_id' => $privateGroup->id,
        'day_of_week' => 6,
        'start_time' => '16:00',
        'end_time' => '17:00',
    ]);

    $this->assertDatabaseHas('teaching_group_schedules', [
        'teaching_group_id' => $privateGroup->id,
        'day_of_week' => 2,
        'start_time' => '16:00',
        'end_time' => '17:00',
    ]);
});

it('exposes the 2-day weekly private group on the public teacher profile and prevents second student booking', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.teaching-schedule.private-groups.store', $this->assignment->id), [
            'days' => [0, 3], // Sunday & Wednesday
            'start_time' => '18:00',
            'duration_minutes' => 60,
        ]);

    $privateGroup = TeachingGroup::where('teaching_assignment_id', $this->assignment->id)
        ->where('is_private', true)
        ->first();

    $this->get(route('teachers.show', $this->teacher->id))
        ->assertInertia(fn ($page) => $page
            ->has('assignments.0.private_groups', 1)
            ->where('assignments.0.private_groups.0.id', $privateGroup->id)
            ->where('assignments.0.private_groups.0.capacity', 1)
            ->where('assignments.0.private_groups.0.seats_left', 1)
        );

    $student = User::factory()->create();
    $student->assignRole('student');

    $subService = app(\App\Application\Subscription\Services\SubscriptionService::class);
    $sub = $subService->openForGroup($student, $privateGroup);
    $subService->activate($sub);

    // Now private group is full (1/1)
    $this->get(route('teachers.show', $this->teacher->id))
        ->assertInertia(fn ($page) => $page
            ->where('assignments.0.private_groups.0.seats_left', 0)
        );

    // Another student cannot subscribe because capacity is 1
    $otherStudent = User::factory()->create();
    $otherStudent->assignRole('student');

    expect(fn () => $subService->openForGroup($otherStudent, $privateGroup))
        ->toThrow(\LogicException::class, 'اكتمل عدد طلاب هذه المجموعة.');
});

