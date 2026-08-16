<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Communication\Notifications\StudentLiveSessionActivityNotification;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->teacher = User::factory()->create(['email_verified_at' => now()]);
    $this->teacher->assignRole('teacher');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => Subject::query()->firstOrFail()->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
    ]);

    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->student->assignRole('student');

    $this->parent = User::factory()->create(['email_verified_at' => now()]);
    $this->parent->assignRole('parent');

    ParentStudentLink::create([
        'parent_user_id' => $this->parent->id,
        'student_user_id' => $this->student->id,
        'relationship' => 'father',
        'verified_at' => now(),
    ]);

    Subscription::factory()->active()->create([
        'student_id' => $this->student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
    ]);

    SessionBooking::create([
        'student_id' => $this->student->id,
        'teaching_group_id' => $this->group->id,
        'status' => 'confirmed',
        'booked_at' => now(),
    ]);

    $this->session = LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'مراجعة مباشرة',
        'scheduled_at' => now()->addHour(),
        'started_at' => now(),
        'status' => LiveSession::STATUS_LIVE,
    ]);
});

it('serves Jitsi details and advertises server attendance endpoints', function (): void {
    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Live/LiveSessionRoom')
            ->where('user.id', $this->student->id)
            ->where('user.isTeacher', false)
            ->where('jitsi.domain', 'meet.jit.si')
            ->where('jitsi.whiteboard.enabled', true)
            ->where('jitsi.whiteboard.collabServerBaseUrl', 'https://meet.jit.si')
            ->where('jitsi.recording.enabled', true)
            ->where('jitsi.recording.mode', 'file')
            ->where('roomName', fn ($roomName) => str_starts_with($roomName, "altafawwuq-{$this->session->id}-")));

    expect(Route::has('live-sessions.attendance.join'))->toBeTrue()
        ->and(Route::has('live-sessions.attendance.leave'))->toBeTrue();
});

it('does not expose a stale live room after its live window has elapsed', function (): void {
    $this->session->update([
        'scheduled_at' => now()->subDays(20),
        'started_at' => now()->subDays(20),
    ]);

    expect($this->session->fresh()->isLive())->toBeFalse();

    $this->actingAs($this->student)
        ->get(route('student.schedule'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('sessions', []));

    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertForbidden();
});

it('records a student live-room visit and notifies the linked parent', function (): void {
    Notification::fake();

    $this->actingAs($this->student)
        ->postJson(route('live-sessions.attendance.join', $this->session->id))
        ->assertOk()
        ->assertJson(['joined' => true]);

    $attendee = LiveSessionAttendee::where('live_session_id', $this->session->id)
        ->where('user_id', $this->student->id)
        ->firstOrFail();

    expect($attendee->joined_at)->not->toBeNull()
        ->and($attendee->left_at)->toBeNull();

    Notification::assertSentTo(
        $this->parent,
        StudentLiveSessionActivityNotification::class,
        fn (StudentLiveSessionActivityNotification $notification): bool =>
            $notification->activity === StudentLiveSessionActivityNotification::ACTIVITY_JOINED,
    );

    $this->actingAs($this->student)
        ->postJson(route('live-sessions.attendance.leave', $this->session->id))
        ->assertOk()
        ->assertJson(['left' => true]);

    expect($attendee->fresh()->left_at)->not->toBeNull();

    Notification::assertSentTo(
        $this->parent,
        StudentLiveSessionActivityNotification::class,
        fn (StudentLiveSessionActivityNotification $notification): bool =>
            $notification->activity === StudentLiveSessionActivityNotification::ACTIVITY_LEFT,
    );
});

it('lets the teacher enter a scheduled Jitsi room for setup without creating student attendance', function (): void {
    $this->session->update(['status' => LiveSession::STATUS_SCHEDULED]);

    $this->actingAs($this->teacher)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('user.id', $this->teacher->id)
            ->where('user.isTeacher', true)
            ->where('jitsi.whiteboard.enabled', true)
            ->where('jitsi.whiteboard.collabServerBaseUrl', 'https://meet.jit.si')
            ->where('jitsi.recording.enabled', true)
            ->where('jitsi.recording.mode', 'file'));

});

it('does not advertise an unusable whiteboard for an unconfigured self-hosted Jitsi server', function (): void {
    config()->set('services.jitsi.domain', 'jitsi.example.test');
    config()->set('services.jitsi.whiteboard.collab_server_base_url', null);

    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('jitsi.whiteboard.enabled', false)
            ->where('jitsi.whiteboard.collabServerBaseUrl', null));
});

it('lets only the teacher start from the room and end the live class', function (): void {
    Notification::fake();

    $this->session->update([
        'status' => LiveSession::STATUS_SCHEDULED,
        'started_at' => null,
        'ended_at' => null,
    ]);

    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertForbidden();

    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.status', $this->session->id), [
            'status' => LiveSession::STATUS_LIVE,
        ])
        ->assertStatus(422);

    $this->actingAs($this->teacher)
        ->postJson(route('teacher.live-sessions.start', $this->session->id))
        ->assertOk()
        ->assertJsonPath('status', LiveSession::STATUS_LIVE);

    expect($this->session->fresh()->status)->toBe(LiveSession::STATUS_LIVE)
        ->and($this->session->fresh()->started_at)->not->toBeNull();

    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertOk();

    $this->session->attendees()->create([
        'user_id' => $this->student->id,
        'joined_at' => now(),
    ]);

    $this->actingAs($this->student)
        ->postJson(route('teacher.live-sessions.end', $this->session->id))
        ->assertForbidden();

    $this->actingAs($this->teacher)
        ->postJson(route('teacher.live-sessions.end', $this->session->id))
        ->assertOk()
        ->assertJsonPath('status', LiveSession::STATUS_ENDED);

    expect($this->session->fresh()->status)->toBe(LiveSession::STATUS_ENDED);

    Notification::assertSentTo(
        $this->parent,
        StudentLiveSessionActivityNotification::class,
        fn (StudentLiveSessionActivityNotification $notification): bool =>
            $notification->activity === StudentLiveSessionActivityNotification::ACTIVITY_LEFT,
    );

    Notification::assertSentTo(
        $this->parent,
        StudentLiveSessionActivityNotification::class,
        fn (StudentLiveSessionActivityNotification $notification): bool =>
            $notification->activity === StudentLiveSessionActivityNotification::ACTIVITY_ENDED,
    );
});

it('starts and serves a Jitsi room without an external meeting link', function (): void {
    $this->session->update([
        'status' => LiveSession::STATUS_SCHEDULED,
        'started_at' => null,
    ]);

    $this->actingAs($this->teacher)
        ->postJson(route('teacher.live-sessions.start', $this->session->id))
        ->assertOk()
        ->assertJsonPath('status', LiveSession::STATUS_LIVE);

    expect($this->session->fresh()->status)->toBe(LiveSession::STATUS_LIVE)
        ->and($this->session->fresh()->started_at)->not->toBeNull();

    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Live/LiveSessionRoom')
            ->where('jitsi.domain', 'meet.jit.si'));
});

it('does not reopen an ended class or serve its Jitsi room', function (): void {
    $this->session->update([
        'status' => LiveSession::STATUS_ENDED,
        'ended_at' => now(),
    ]);

    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.status', $this->session->id), [
            'status' => LiveSession::STATUS_LIVE,
        ])
        ->assertStatus(422);

    $this->actingAs($this->teacher)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertForbidden();

    expect($this->session->fresh()->status)->toBe(LiveSession::STATUS_ENDED);
});

it('requires the active group subscription for Jitsi entry', function (): void {
    $otherGroup = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
    ]);

    $this->student->subscriptions()->update([
        'teaching_group_id' => $otherGroup->id,
    ]);

    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertForbidden();
});

it('requires private JWT Jitsi configuration when authentication is enabled', function (): void {
    config()->set('services.jitsi.require_auth', true);
    config()->set('services.jitsi.domain', 'meet.jit.si');
    config()->set('services.jitsi.app_id', null);
    config()->set('services.jitsi.app_secret', null);

    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertStatus(503);

    config()->set('services.jitsi.app_id', 'altafawwuq');
    config()->set('services.jitsi.app_secret', 'test-jitsi-secret');
    config()->set('services.jitsi.domain', 'MEET.JIT.SI:443');

    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertStatus(503);

    config()->set('services.jitsi.domain', 'jitsi.example.test');

    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('jitsi.domain', 'jitsi.example.test')
            ->where('jitsi.jwt', fn ($jwt) => filled($jwt)));
});

it('schedules a Jitsi room, rejects legacy external links, and blocks another class at the same time', function (): void {
    $timezone = $this->group->timezone;
    $date = Carbon::now($timezone)->addDay()->startOfDay();

    while ($date->dayOfWeek !== (int) $this->group->day_of_week) {
        $date->addDay();
    }

    $payload = [
        'source_type' => 'group',
        'teaching_group_id' => $this->group->id,
        'scheduled_date' => $date->toDateString(),
        'title' => 'حصة Jitsi داخل المنصة',
    ];

    $this->actingAs($this->teacher)
        ->post(route('teacher.live-sessions.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $scheduled = LiveSession::where('title', 'حصة Jitsi داخل المنصة')->firstOrFail();
    expect(array_key_exists('room_id', $scheduled->getAttributes()))->toBeFalse();

    $this->actingAs($this->teacher)
        ->post(route('teacher.live-sessions.store'), [
            ...$payload,
            'title' => 'رابط خارجي مرفوض',
            'room_id' => 'https://meet.google.com/abc-defg-hij',
        ])
        ->assertSessionHasErrors('room_id');

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
