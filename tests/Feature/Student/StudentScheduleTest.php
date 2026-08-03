<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shows only the signed-in students upcoming confirmed classes', function (): void {
    /** @var \Tests\TestCase $this */
    foreach (['student', 'teacher'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');

    $student = User::factory()->create(['email_verified_at' => now()]);
    $student->assignRole('student');

    $otherStudent = User::factory()->create(['email_verified_at' => now()]);
    $otherStudent->assignRole('student');

    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => Subject::factory()->create(['name' => 'الفيزياء'])->id,
        'grade_level_id' => GradeLevel::where('key', 'grade_12_science')->firstOrFail()->id,
    ]);

    $studentGroup = TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id]);
    $otherGroup = TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id]);

    SessionBooking::create([
        'student_id' => $student->id,
        'teaching_group_id' => $studentGroup->id,
        'status' => 'confirmed',
        'booked_at' => now(),
    ]);
    SessionBooking::create([
        'student_id' => $otherStudent->id,
        'teaching_group_id' => $otherGroup->id,
        'status' => 'confirmed',
        'booked_at' => now(),
    ]);

    $visible = LiveSession::create([
        'teacher_id' => $teacher->id,
        'teaching_group_id' => $studentGroup->id,
        'title' => 'الحصة التي تخص الطالب',
        'scheduled_at' => now()->addDay(),
        'status' => LiveSession::STATUS_SCHEDULED,
        'room_id' => 'https://meet.example.test/private-room',
    ]);

    LiveSession::create([
        'teacher_id' => $teacher->id,
        'teaching_group_id' => $otherGroup->id,
        'title' => 'حصة طالب آخر',
        'scheduled_at' => now()->addDay(),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    LiveSession::create([
        'teacher_id' => $teacher->id,
        'teaching_group_id' => $studentGroup->id,
        'title' => 'حصة قديمة',
        'scheduled_at' => now()->subDay(),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    $response = $this->actingAs($student)->get(route('student.schedule'));

    $response->assertOk()->assertInertia(function ($page) use ($visible): void {
        $page->component('Student/Schedule')
            ->has('sessions', 1)
            ->where('sessions.0.id', $visible->id)
            ->where('sessions.0.subject', 'الفيزياء')
            ->where('sessions.0.type', 'group')
            ->missing('sessions.0.room_id');
    });
});

it('does not allow a non-student to open the student schedule', function (): void {
    /** @var \Tests\TestCase $this */
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');

    $this->actingAs($teacher)
        ->get(route('student.schedule'))
        ->assertForbidden();
});
