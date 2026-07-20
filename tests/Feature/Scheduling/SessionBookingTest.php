<?php

declare(strict_types=1);

use App\Application\Scheduling\Services\SessionBookingService;
use App\Domain\Course\Models\GradeLevel;
use App\Domain\Course\Models\Subject;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Tests\TestCase::class, RefreshDatabase::class);

function schedulingAssignment(User $teacher): TeachingAssignment
{
    $grade = GradeLevel::create(['key' => 'grade_test', 'name' => 'Test Grade', 'name_en' => 'Test Grade', 'stage' => 'secondary', 'is_active' => true]);
    $subject = Subject::create(['name' => 'Test Subject', 'name_en' => 'Test Subject', 'grade_level' => $grade->key, 'icon' => 'book', 'is_active' => true]);

    return TeachingAssignment::create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'grade_level_id' => $grade->id, 'is_active' => true]);
}

it('enforces group capacity and prevents duplicate group bookings', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');
    $student = User::factory()->create();
    $student->assignRole('student');
    $otherStudent = User::factory()->create();
    $otherStudent->assignRole('student');
    $assignment = schedulingAssignment($teacher);
    $group = TeachingGroup::create([
        'teaching_assignment_id' => $assignment->id, 'name' => 'A', 'capacity' => 1,
        'day_of_week' => 1, 'start_time' => '16:00', 'end_time' => '17:00',
        'duration_minutes' => 60, 'timezone' => 'Asia/Qatar', 'is_active' => true,
    ]);
    $service = app(SessionBookingService::class);

    $service->bookGroup($student, $group->id);
    expect(fn () => $service->bookGroup($student, $group->id))->toThrow(LogicException::class);
    expect(fn () => $service->bookGroup($otherStudent, $group->id))->toThrow(LogicException::class);
    expect(SessionBooking::count())->toBe(1);
});

it('allows only one student to book a private slot and restores it on cancellation', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');
    $student = User::factory()->create();
    $student->assignRole('student');
    $otherStudent = User::factory()->create();
    $otherStudent->assignRole('student');
    $assignment = schedulingAssignment($teacher);
    $slot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $assignment->id,
        'starts_at' => now()->addDay()->setTime(16, 0),
        'ends_at' => now()->addDay()->setTime(17, 0),
        'timezone' => 'Asia/Qatar', 'status' => 'available',
    ]);
    $service = app(SessionBookingService::class);

    $booking = $service->bookPrivate($student, $slot->id);
    expect($slot->fresh()->status)->toBe('booked');
    expect(fn () => $service->bookPrivate($otherStudent, $slot->id))->toThrow(LogicException::class);

    $service->cancel($student, $booking->id);
    expect($slot->fresh()->status)->toBe('available');
});

it('prevents teachers from booking student schedules', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $this->actingAs($teacher)->get(route('student.session-booking'))->assertForbidden();
});
