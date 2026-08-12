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
use Illuminate\Support\Carbon;
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

it('serves Jitsi details without allowing a browser to self-record attendance', function (): void {
    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Live/LiveSessionRoom')
            ->where('user.id', $this->student->id)
            ->where('user.isTeacher', false)
            ->where('jitsi.domain', 'meet.jit.si')
            ->where('jitsi.whiteboard.enabled', true)
            ->where('roomName', fn ($roomName) => str_starts_with($roomName, "altafawwuq-{$this->session->id}-")));

    expect(Route::has('live-sessions.attendance.join'))->toBeFalse()
        ->and(Route::has('live-sessions.attendance.leave'))->toBeFalse();
});

it('lets the teacher enter a scheduled Jitsi room for setup without creating student attendance', function (): void {
    $this->session->update(['status' => LiveSession::STATUS_SCHEDULED]);

    $this->actingAs($this->teacher)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('user.id', $this->teacher->id)
            ->where('user.isTeacher', true)
            ->where('jitsi.whiteboard.enabled', true));

});

it('starts and serves a Jitsi room without an external meeting link', function (): void {
    $this->session->update([
        'status' => LiveSession::STATUS_SCHEDULED,
        'started_at' => null,
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
