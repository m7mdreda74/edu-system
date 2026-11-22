<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function teacherGroupFixture(): array
{
    Role::findOrCreate('teacher', 'web');
    Role::findOrCreate('student', 'web');

    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');
    $student = User::factory()->create(['name' => 'Roster Student']);
    $student->assignRole('student');
    $subject = Subject::query()->firstOrFail();
    $grade = GradeLevel::query()->firstOrFail();
    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'grade_level_id' => $grade->id,
    ]);
    $group = TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id]);

    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_assignment_id' => $assignment->id,
        'teaching_group_id' => $group->id,
    ]);
    SessionBooking::create([
        'student_id' => $student->id,
        'teaching_group_id' => $group->id,
        'status' => 'confirmed',
        'booked_at' => now(),
    ]);

    return compact('teacher', 'student', 'assignment', 'group');
}

it('keeps the group page light and loads a teacher-owned roster through a paginated endpoint', function (): void {
    $fixture = teacherGroupFixture();

    $this->actingAs($fixture['teacher'])
        ->get(route('teacher.groups.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/Groups')
            ->where('stats.total_students', 1)
            ->where('groups.0.students_count', 1)
            ->missing('groups.0.students'));

    $this->actingAs($fixture['teacher'])
        ->getJson(route('teacher.groups.roster', ['groupId' => $fixture['group']->id]))
        ->assertOk()
        ->assertJsonPath('data.0.id', $fixture['student']->id)
        ->assertJsonPath('meta.total', 1);

    $this->actingAs($fixture['teacher'])
        ->get(route('teacher.groups.workspace', ['groupId' => $fixture['group']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/GroupWorkspace')
            ->where('studentsCount', 1));

    $this->actingAs($fixture['teacher'])
        ->get(route('teacher.groups.roster.page', ['groupId' => $fixture['group']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/GroupRoster')
            ->where('studentsCount', 1));
});

it('does not expose another teacher group workspace or roster', function (): void {
    $fixture = teacherGroupFixture();
    $otherTeacher = User::factory()->create(['email_verified_at' => now()]);
    $otherTeacher->assignRole('teacher');

    $this->actingAs($otherTeacher)
        ->get(route('teacher.groups.workspace', ['groupId' => $fixture['group']->id]))
        ->assertNotFound();

    $this->actingAs($otherTeacher)
        ->getJson(route('teacher.groups.roster', ['groupId' => $fixture['group']->id]))
        ->assertNotFound();
});

it('provides filter fields and only the owning teacher groups and private bookings', function (): void {
    $fixture = teacherGroupFixture();
    $grade = $fixture['assignment']->gradeLevel;
    $grade->update(['stage' => 'secondary', 'track' => 'science']);
    $fixture['group']->update(['capacity' => 1, 'is_private' => false]);

    $otherTeacher = User::factory()->create(['email_verified_at' => now()]);
    $otherTeacher->assignRole('teacher');
    $otherAssignment = TeachingAssignment::factory()->create([
        'teacher_id' => $otherTeacher->id,
        'subject_id' => $fixture['assignment']->subject_id,
        'grade_level_id' => $grade->id,
    ]);
    TeachingGroup::factory()->create(['teaching_assignment_id' => $otherAssignment->id]);

    $slot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $fixture['assignment']->id,
        'starts_at' => '2026-12-10 15:00:00',
        'ends_at' => '2026-12-10 16:00:00',
        'timezone' => 'Asia/Qatar',
        'status' => 'booked',
    ]);
    $otherSlot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $otherAssignment->id,
        'starts_at' => '2026-12-11 15:00:00',
        'ends_at' => '2026-12-11 16:00:00',
        'timezone' => 'Asia/Qatar',
        'status' => 'booked',
    ]);
    foreach ([$slot, $otherSlot] as $bookedSlot) {
        SessionBooking::create(['student_id' => $fixture['student']->id, 'private_session_slot_id' => $bookedSlot->id, 'status' => 'confirmed']);
    }

    $this->actingAs($fixture['teacher'])
        ->get(route('teacher.groups.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('groups', 1)
            ->where('groups.0.id', $fixture['group']->id)
            ->where('groups.0.source', 'group')
            ->where('groups.0.is_private', true)
            ->where('groups.0.grade.stage', 'secondary')
            ->where('groups.0.grade.track', 'science')
            ->where('groups.0.subject.id', $fixture['assignment']->subject_id)
            ->has('privateSlots', 1)
            ->where('privateSlots.0.id', $slot->id)
            ->where('privateSlots.0.source', 'private_slot')
            ->where('privateSlots.0.is_private', true)
            ->where('privateSlots.0.grade.id', $grade->id)
            ->where('privateSlots.0.grade.stage', 'secondary')
            ->where('privateSlots.0.grade.track', 'science')
            ->where('privateSlots.0.students.0.id', $fixture['student']->id));
});
