<?php

declare(strict_types=1);

use App\Application\Learning\Services\LiveSessionReminderService;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Communication\Notifications\LiveSessionReminderNotification;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

abstract class LiveSessionReminderTestCase extends TestCase
{
    public User $teacher;
    public User $student;
    public TeachingAssignment $assignment;
    public TeachingGroup $group;
}

uses(LiveSessionReminderTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    /** @var LiveSessionReminderTestCase $this */
    Carbon::setTestNow(Carbon::parse('2026-08-04 09:00:00', 'UTC'));

    foreach (['student', 'teacher'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->teacher = User::factory()->create(['email_verified_at' => now()]);
    $this->teacher->assignRole('teacher');

    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->student->assignRole('student');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => Subject::factory()->create(['name' => 'الرياضيات'])->id,
        'grade_level_id' => GradeLevel::where('key', 'grade_12_science')->firstOrFail()->id,
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'timezone' => 'Asia/Qatar',
    ]);

    SessionBooking::create([
        'student_id' => $this->student->id,
        'teaching_group_id' => $this->group->id,
        'status' => 'confirmed',
        'booked_at' => now(),
    ]);
});

afterEach(function (): void {
    /** @var LiveSessionReminderTestCase $this */
    Carbon::setTestNow();
});

it('notifies a confirmed student once when a class enters the next 24 hours', function (): void {
    /** @var LiveSessionReminderTestCase $this */
    Notification::fake();

    $session = LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'مراجعة التفاضل',
        'scheduled_at' => now()->addHours(23),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    $service = app(LiveSessionReminderService::class);

    expect($service->sendDueReminders())->toBe(1)
        ->and($service->sendDueReminders())->toBe(0);

    Notification::assertSentTo(
        $this->student,
        LiveSessionReminderNotification::class,
        function (LiveSessionReminderNotification $notification) use ($session): bool {
            $data = $notification->toArray($this->student);

            return $notification->session->is($session)
                && $data['type'] === 'live_session_reminder'
                && $data['link'] === route('student.schedule')
                && str_contains($data['message'], 'الرياضيات');
        },
    );

    $this->assertDatabaseHas('live_session_reminders', [
        'live_session_id' => $session->id,
        'student_id' => $this->student->id,
    ]);
});

it('waits until the class is within 24 hours and ignores cancelled bookings', function (): void {
    /** @var LiveSessionReminderTestCase $this */
    Notification::fake();

    LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'حصة الأسبوع القادم',
        'scheduled_at' => now()->addHours(25),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    SessionBooking::where('student_id', $this->student->id)->update(['status' => 'cancelled']);

    LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'حصة بدون حجز مؤكد',
        'scheduled_at' => now()->addHours(12),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    expect(app(LiveSessionReminderService::class)->sendDueReminders())->toBe(0);
    Notification::assertNothingSent();
});

it('reminds the student about a confirmed private class too', function (): void {
    /** @var LiveSessionReminderTestCase $this */
    Notification::fake();
    SessionBooking::where('student_id', $this->student->id)->update(['status' => 'cancelled']);

    $slot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $this->assignment->id,
        'starts_at' => now()->addHours(18),
        'ends_at' => now()->addHours(19),
        'timezone' => 'Asia/Qatar',
        'status' => 'booked',
    ]);

    SessionBooking::create([
        'student_id' => $this->student->id,
        'private_session_slot_id' => $slot->id,
        'status' => 'confirmed',
        'booked_at' => now(),
    ]);

    LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'private_session_slot_id' => $slot->id,
        'title' => 'حصة خاصة في التفاضل',
        'scheduled_at' => $slot->starts_at,
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    expect(app(LiveSessionReminderService::class)->sendDueReminders())->toBe(1);
    Notification::assertSentTo($this->student, LiveSessionReminderNotification::class);
});

it('protects the reminder cron endpoint and reports the sent notification count', function (): void {
    /** @var LiveSessionReminderTestCase $this */
    Notification::fake();
    config()->set('services.vercel.cron_secret', 'test-cron-secret');

    LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'حصة قريبة',
        'scheduled_at' => now()->addHours(20),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    $this->getJson(route('cron.live-session-reminders'))->assertUnauthorized();

    $this->withHeader('Authorization', 'Bearer test-cron-secret')
        ->getJson(route('cron.live-session-reminders'))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'notifications_sent' => 1,
        ]);
});
