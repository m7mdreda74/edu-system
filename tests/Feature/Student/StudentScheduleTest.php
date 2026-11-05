<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shows the signed-in students upcoming and past live sessions grouped with subjects and groups without assessments', function (): void {
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

    $physicsSubject = Subject::factory()->create(['name' => 'الفيزياء']);
    $mathSubject = Subject::factory()->create(['name' => 'الرياضيات']);
    $gradeId = GradeLevel::where('key', 'grade_12_science')->firstOrFail()->id;

    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $physicsSubject->id,
        'grade_level_id' => $gradeId,
    ]);

    $mathAssignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $mathSubject->id,
        'grade_level_id' => $gradeId,
    ]);

    $studentGroup = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'name' => 'مجموعة الفيزياء الأولى',
    ]);
    $mathGroup = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $mathAssignment->id,
        'name' => 'مجموعة الرياضيات الثانية',
    ]);
    $otherGroup = TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id]);

    SessionBooking::create([
        'student_id' => $student->id,
        'teaching_group_id' => $studentGroup->id,
        'status' => 'confirmed',
        'booked_at' => now(),
    ]);
    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_assignment_id' => $mathAssignment->id,
        'teaching_group_id' => $mathGroup->id,
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
    ]);

    $endedMathSession = LiveSession::create([
        'teacher_id' => $teacher->id,
        'teaching_group_id' => $mathGroup->id,
        'title' => 'حصة رياضيات سابقة',
        'scheduled_at' => now()->subDays(2),
        'started_at' => now()->subDays(2),
        'ended_at' => now()->subDays(2)->addHour(),
        'status' => LiveSession::STATUS_ENDED,
    ]);

    LiveSession::create([
        'teacher_id' => $teacher->id,
        'teaching_group_id' => $otherGroup->id,
        'title' => 'حصة طالب آخر',
        'scheduled_at' => now()->addDay(),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    $cancelledOld = LiveSession::create([
        'teacher_id' => $teacher->id,
        'teaching_group_id' => $studentGroup->id,
        'title' => 'حصة قديمة',
        'scheduled_at' => now()->subDay(),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    $response = $this->actingAs($student)->get(route('student.schedule'));

    $response->assertOk()->assertInertia(function ($page) use ($visible, $endedMathSession, $cancelledOld, $physicsSubject, $mathSubject): void {
        $page->component('Student/Schedule')
            ->missing('homework')
            ->missing('assessments')
            ->has('sessions', 1)
            ->where('sessions.0.id', $visible->id)
            ->where('sessions.0.subject', 'الفيزياء')
            ->where('sessions.0.subject_id', $physicsSubject->id)
            ->where('sessions.0.type', 'group')
            ->missing('sessions.0.room_id')
            ->has('pastSessions', 2)
            ->where('pastSessions.0.id', $cancelledOld->id)
            ->where('pastSessions.1.id', $endedMathSession->id)
            ->where('pastSessions.1.subject', 'الرياضيات')
            ->where('pastSessions.1.subject_id', $mathSubject->id)
            ->has('subjects', 2)
            ->has('groups', 2);
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
