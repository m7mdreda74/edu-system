<?php

declare(strict_types=1);

use App\Application\Scheduling\Services\TeacherPrivateSlotsForGroups;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shows only confirmed private bookings on the owning active assignment with their actual details', function (): void {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $student = User::factory()->create(['name' => 'Confirmed Student']);
    $cancelledStudent = User::factory()->create(['name' => 'Cancelled Student']);
    $subject = Subject::query()->firstOrFail();
    $grade = GradeLevel::query()->firstOrFail();
    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'grade_level_id' => $grade->id,
        'is_active' => true,
    ]);
    $otherAssignment = TeachingAssignment::factory()->create([
        'teacher_id' => $otherTeacher->id,
        'subject_id' => $subject->id,
        'grade_level_id' => $grade->id,
        'is_active' => true,
    ]);
    $slot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $assignment->id,
        'starts_at' => '2026-12-10 22:30:00',
        'ends_at' => '2026-12-10 23:30:00',
        'timezone' => 'Asia/Qatar',
        'status' => 'booked',
    ]);
    SessionBooking::create(['student_id' => $student->id, 'private_session_slot_id' => $slot->id, 'status' => 'confirmed']);
    // A later cancelled booking must not hide the confirmed booking.
    SessionBooking::create(['student_id' => $cancelledStudent->id, 'private_session_slot_id' => $slot->id, 'status' => 'cancelled']);

    $otherSlot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $otherAssignment->id,
        'starts_at' => '2026-12-11 15:30:00',
        'ends_at' => '2026-12-11 16:30:00',
        'timezone' => 'Asia/Qatar',
        'status' => 'booked',
    ]);
    SessionBooking::create(['student_id' => $cancelledStudent->id, 'private_session_slot_id' => $otherSlot->id, 'status' => 'confirmed']);

    $cancelledSlot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $assignment->id,
        'starts_at' => '2026-12-12 15:30:00',
        'ends_at' => '2026-12-12 16:30:00',
        'timezone' => 'Asia/Qatar',
        'status' => 'cancelled',
    ]);
    SessionBooking::create(['student_id' => $cancelledStudent->id, 'private_session_slot_id' => $cancelledSlot->id, 'status' => 'confirmed']);

    $unbookedSlot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $assignment->id,
        'starts_at' => '2026-12-13 15:30:00',
        'ends_at' => '2026-12-13 16:30:00',
        'timezone' => 'Asia/Qatar',
        'status' => 'available',
    ]);
    SessionBooking::create(['student_id' => $cancelledStudent->id, 'private_session_slot_id' => $unbookedSlot->id, 'status' => 'cancelled']);

    $service = app(TeacherPrivateSlotsForGroups::class);
    $slots = $service->forTeacher($teacher->id);
    expect($slots)->toHaveCount(1)
        ->and($slots[0]['source'])->toBe('private_slot')
        ->and($slots[0]['students_count'])->toBe(1)
        ->and($slots[0]['students'][0]['id'])->toBe($student->id)
        ->and($slots[0]['schedules'][0]['date'])->toBe('2026-12-11')
        ->and($slots[0]['schedules'][0]['day_of_week'])->toBe(5)
        ->and($slots[0]['schedules'][0]['start_time'])->toBe('01:30')
        ->and($slots[0]['schedules'][0]['end_time'])->toBe('02:30');

    $assignment->update(['is_active' => false]);
    expect($service->forTeacher($teacher->id))->toBeEmpty();
});
