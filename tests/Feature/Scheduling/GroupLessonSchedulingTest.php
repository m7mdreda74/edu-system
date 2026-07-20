<?php

declare(strict_types=1);

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\GradeLevel;
use App\Domain\Course\Models\LiveSession;
use App\Domain\Course\Models\Subject;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupLesson;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('schedules the next planned group lesson on the nearest group day', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00', 'Asia/Qatar')); // Monday
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');
    $grade = GradeLevel::firstOrFail();
    $subject = Subject::factory()->create();
    Course::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'grade_level' => $grade->key,
        'is_published' => true,
    ]);
    $assignment = TeachingAssignment::create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'grade_level_id' => $grade->id,
        'is_active' => true,
    ]);
    $group = TeachingGroup::create([
        'teaching_assignment_id' => $assignment->id,
        'name' => 'المجموعة أ',
        'capacity' => 15,
        'day_of_week' => 2,
        'start_time' => '17:00',
        'end_time' => '18:00',
        'duration_minutes' => 60,
        'timezone' => 'Asia/Qatar',
        'is_active' => true,
    ]);
    $group->schedules()->createMany([
        ['day_of_week' => 2, 'start_time' => '17:00', 'end_time' => '18:00', 'duration_minutes' => 60],
        ['day_of_week' => 6, 'start_time' => '15:00', 'end_time' => '16:00', 'duration_minutes' => 60],
    ]);
    $first = $group->lessons()->create(['position' => 1, 'title' => 'شرح الدرس الأول', 'status' => 'pending']);
    $second = $group->lessons()->create(['position' => 2, 'title' => 'حل تدريبات', 'status' => 'pending']);

    $this->actingAs($teacher)
        ->post(route('teacher.teaching-schedule.group-lessons.schedule', $second->id))
        ->assertStatus(422);

    $this->actingAs($teacher)
        ->post(route('teacher.teaching-schedule.group-lessons.schedule', $first->id))
        ->assertRedirect();

    $session = LiveSession::firstOrFail();
    expect($session->teaching_group_id)->toBe($group->id)
        ->and($session->title)->toBe('شرح الدرس الأول')
        ->and($session->scheduled_at->setTimezone('Asia/Qatar')->format('Y-m-d H:i'))->toBe('2026-07-21 17:00')
        ->and(TeachingGroupLesson::find($first->id)->status)->toBe('scheduled');

    Carbon::setTestNow();
});
