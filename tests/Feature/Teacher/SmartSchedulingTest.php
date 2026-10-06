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
