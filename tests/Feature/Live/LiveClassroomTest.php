<?php

declare(strict_types=1);

use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// Binds $this inside every Pest closure in this file to Tests\TestCase.
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->teacher = User::factory()->create(['email_verified_at' => now()]);
    $this->teacher->assignRole('teacher');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id'     => $this->teacher->id,
        'subject_id'     => Subject::query()->firstOrFail()->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
    ]);

    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->student->assignRole('student');

    Subscription::factory()->active()->create([
        'student_id'             => $this->student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id'      => $this->group->id,
    ]);

    SessionBooking::create([
        'student_id'        => $this->student->id,
        'teaching_group_id' => $this->group->id,
        'status'            => 'confirmed',
        'booked_at'         => now(),
    ]);

    $this->session = LiveSession::create([
        'teacher_id'       => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title'            => 'مراجعة مباشرة',
        'scheduled_at'     => now()->addHour(),
        'started_at'       => now(),
        'status'           => LiveSession::STATUS_LIVE,
    ]);
});

it('records a subscribed student attendance window and exposes the user id to WebRTC', function () {
    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Live/LiveSessionRoom')
            ->where('user.id', $this->student->id)
            ->where('user.isTeacher', false));

    $this->actingAs($this->student)
        ->postJson(route('webrtc.heartbeat', $this->session->id))
        ->assertOk()
        ->assertJsonPath('participants', []);

    expect(LiveSessionAttendee::where('live_session_id', $this->session->id)
        ->where('user_id', $this->student->id)
        ->whereNull('left_at')
        ->exists())->toBeTrue();

    $this->actingAs($this->student)
        ->postJson(route('webrtc.leave', $this->session->id))
        ->assertOk();

    expect(LiveSessionAttendee::where('live_session_id', $this->session->id)
        ->where('user_id', $this->student->id)
        ->whereNotNull('left_at')
        ->exists())->toBeTrue();
});

it('rejects WebRTC traffic from a student without a confirmed seat', function () {
    $outsider = User::factory()->create(['email_verified_at' => now()]);
    $outsider->assignRole('student');

    $this->actingAs($outsider)
        ->postJson(route('webrtc.heartbeat', $this->session->id))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->postJson(route('webrtc.signal', $this->session->id), [
            'type'    => 'chat',
            'payload' => ['text' => 'غير مصرح'],
        ])
        ->assertForbidden();
});

it('lets the teacher enter before the class is live for setup', function () {
    $this->session->update(['status' => LiveSession::STATUS_SCHEDULED]);

    $this->actingAs($this->teacher)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('user.id', $this->teacher->id)
            ->where('user.isTeacher', true));

    $this->actingAs($this->teacher)
        ->postJson(route('webrtc.heartbeat', $this->session->id))
        ->assertOk();
});

it('starts and serves the platform room without an external meeting link', function () {
    $this->session->update([
        'status' => LiveSession::STATUS_SCHEDULED,
        'started_at' => null,
        'room_id' => null,
    ]);

    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.status', $this->session->id), [
            'status' => LiveSession::STATUS_LIVE,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->session->fresh()->status)->toBe(LiveSession::STATUS_LIVE)
        ->and($this->session->fresh()->started_at)->not->toBeNull();

    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Live/LiveSessionRoom'));
});

it('does not reopen an ended class', function () {
    $this->session->update([
        'status' => LiveSession::STATUS_ENDED,
        'ended_at' => now(),
    ]);

    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.status', $this->session->id), [
            'status' => LiveSession::STATUS_LIVE,
        ])
        ->assertStatus(422);

    expect($this->session->fresh()->status)->toBe(LiveSession::STATUS_ENDED);
});

it('requires the active group subscription on WebRTC requests', function () {
    $otherGroup = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
    ]);

    $this->student->subscriptions()->update([
        'teaching_group_id' => $otherGroup->id,
    ]);

    $this->actingAs($this->student)
        ->postJson(route('webrtc.heartbeat', $this->session->id))
        ->assertForbidden();
});

it('schedules an internal room and blocks another class at the same time', function () {
    $timezone = $this->group->timezone;
    $date = Carbon::now($timezone)->addDay()->startOfDay();

    while ($date->dayOfWeek !== (int) $this->group->day_of_week) {
        $date->addDay();
    }

    $payload = [
        'source_type' => 'group',
        'teaching_group_id' => $this->group->id,
        'scheduled_date' => $date->toDateString(),
        'title' => 'حصة داخل المنصة',
        'room_id' => null,
    ];

    $this->actingAs($this->teacher)
        ->post(route('teacher.live-sessions.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $scheduled = LiveSession::where('title', 'حصة داخل المنصة')->firstOrFail();
    expect($scheduled->room_id)->toBeNull();

    $otherGroup = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'day_of_week' => $this->group->day_of_week,
        'start_time' => $this->group->start_time,
        'end_time' => $this->group->end_time,
    ]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.live-sessions.store'), [
            ...$payload,
            'teaching_group_id' => $otherGroup->id,
            'title' => 'حصة متعارضة',
        ])
        ->assertStatus(422);

    expect(LiveSession::where('title', 'حصة متعارضة')->exists())->toBeFalse();
});
