<?php

declare(strict_types=1);

use App\Application\Scheduling\Services\SessionBookingService;
use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\GradeLevel;
use App\Domain\Course\Models\LiveSession;
use App\Domain\Course\Models\Subject;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Tests\TestCase::class, RefreshDatabase::class);

it('only lets the confirmed group students enter a live group session', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');
    $student = User::factory()->create();
    $student->assignRole('student');
    $outsider = User::factory()->create();
    $outsider->assignRole('student');

    $grade = GradeLevel::first();
    $subject = Subject::factory()->create();
    $course = Course::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'grade_level' => $grade->key,
    ]);
    $assignment = TeachingAssignment::create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'grade_level_id' => $grade->id,
        'is_active' => true,
    ]);
    $group = TeachingGroup::create([
        'teaching_assignment_id' => $assignment->id,
        'name' => 'A', 'capacity' => 10, 'day_of_week' => now()->addDay()->dayOfWeek,
        'start_time' => '16:00', 'end_time' => '17:00', 'duration_minutes' => 60,
        'timezone' => 'Asia/Qatar', 'is_active' => true,
    ]);
    $booking = app(SessionBookingService::class)->bookGroup($student, $group->id);
    expect($booking)->toBeInstanceOf(SessionBooking::class);

    $date = now()->addDay()->toDateString();
    $this->actingAs($teacher)->post(route('teacher.live-sessions.store'), [
        'course_id' => $course->id,
        'source_type' => 'group',
        'teaching_group_id' => $group->id,
        'scheduled_date' => $date,
        'title' => 'حصة المجموعة',
        'description' => null,
        'room_id' => null,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $session = LiveSession::latest('id')->firstOrFail();
    $session->update(['status' => 'live', 'scheduled_at' => now()->subMinute()]);

    $this->actingAs($student)->get(route('live-sessions.room', $session->id))->assertOk();
    $this->actingAs($outsider)->get(route('live-sessions.room', $session->id))->assertForbidden();
});

it('does not let students enter before a linked live session starts', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');
    $student = User::factory()->create();
    $student->assignRole('student');

    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
    $session = LiveSession::create([
        'course_id' => $course->id, 'teacher_id' => $teacher->id,
        'title' => 'حصة مجدولة', 'scheduled_at' => now()->addHour(), 'status' => 'scheduled',
    ]);

    $this->actingAs($student)->get(route('live-sessions.room', $session->id))->assertForbidden();
});
