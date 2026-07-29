<?php

declare(strict_types=1);

use App\Application\Subscription\Services\SubscriptionRenewalReminderService;
use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Communication\Notifications\SubscriptionRenewalReminderNotification;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupSchedule;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-29 09:00:00', 'UTC'));

    foreach (['student', 'parent', 'teacher'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $grade = GradeLevel::where('key', 'grade_12_science')->firstOrFail();
    $subject = Subject::factory()->create();

    $this->teacher = User::factory()->create(['email_verified_at' => now()]);
    $this->teacher->assignRole('teacher');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => $subject->id,
        'grade_level_id' => $grade->id,
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'day_of_week' => Carbon::SUNDAY,
        'start_time' => '16:00:00',
        'timezone' => 'Asia/Qatar',
    ]);

    TeachingGroupSchedule::create([
        'teaching_group_id' => $this->group->id,
        'day_of_week' => Carbon::SUNDAY,
        'start_time' => '16:00:00',
        'end_time' => '17:30:00',
        'duration_minutes' => 90,
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

    $this->subscription = Subscription::create([
        'student_id' => $this->student->id,
        'type' => Subscription::TYPE_GROUP,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
        'monthly_price' => 45_000,
        'currency' => 'QAR',
        'period_start' => '2026-07-03',
        'period_end' => '2026-08-03',
        'status' => Subscription::STATUS_ACTIVE,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('notifies the student and verified parent when the next group class is the last one', function () {
    Notification::fake();

    $sent = app(SubscriptionRenewalReminderService::class)->sendDueReminders();

    expect($sent)->toBe(1)
        ->and($this->subscription->fresh()->renewal_reminder_sent_at)->not->toBeNull();

    Notification::assertSentTo(
        $this->student,
        SubscriptionRenewalReminderNotification::class,
        function (SubscriptionRenewalReminderNotification $notification): bool {
            $data = $notification->toArray($this->student);

            return $data['subscription_id'] === $this->subscription->id
                && $data['link'] === route('subscriptions.renewal.show', $this->subscription)
                && str_contains($data['message'], 'آخر حصة');
        },
    );
    Notification::assertSentTo($this->parent, SubscriptionRenewalReminderNotification::class);

    expect(app(SubscriptionRenewalReminderService::class)->sendDueReminders())->toBe(0);
    Notification::assertSentTimes(SubscriptionRenewalReminderNotification::class, 2);
});

it('waits while more than one group class remains in the billing period', function () {
    Notification::fake();
    $this->subscription->update(['period_end' => '2026-08-10']);

    expect(app(SubscriptionRenewalReminderService::class)->sendDueReminders())->toBe(0)
        ->and($this->subscription->fresh()->renewal_reminder_sent_at)->toBeNull();

    Notification::assertNothingSent();
});

it('does not remind a student who already has a renewal awaiting payment', function () {
    Notification::fake();

    Subscription::create([
        'student_id' => $this->student->id,
        'type' => Subscription::TYPE_GROUP,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
        'monthly_price' => 45_000,
        'currency' => 'QAR',
        'period_start' => '2026-08-03',
        'period_end' => '2026-09-03',
        'status' => Subscription::STATUS_PENDING,
    ]);

    expect(app(SubscriptionRenewalReminderService::class)->sendDueReminders())->toBe(0);
    Notification::assertNothingSent();
});

it('supports the final booked private class too', function () {
    Notification::fake();
    $this->subscription->update(['status' => Subscription::STATUS_CANCELLED]);

    $privateSubscription = Subscription::create([
        'student_id' => $this->student->id,
        'type' => Subscription::TYPE_PRIVATE,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => null,
        'monthly_price' => 90_000,
        'currency' => 'QAR',
        'period_start' => '2026-07-03',
        'period_end' => '2026-08-03',
        'status' => Subscription::STATUS_ACTIVE,
    ]);

    $slot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $this->assignment->id,
        'starts_at' => Carbon::parse('2026-08-02 10:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-08-02 11:00:00', 'UTC'),
        'timezone' => 'Asia/Qatar',
        'status' => 'booked',
    ]);

    SessionBooking::create([
        'student_id' => $this->student->id,
        'private_session_slot_id' => $slot->id,
        'status' => 'confirmed',
        'booked_at' => now(),
    ]);

    expect(app(SubscriptionRenewalReminderService::class)->sendDueReminders())->toBe(1)
        ->and($privateSubscription->fresh()->renewal_reminder_sent_at)->not->toBeNull();
});

it('lets a linked parent confirm renewal and continue to checkout without duplicates', function () {
    $this->actingAs($this->parent)
        ->get(route('subscriptions.renewal.show', $this->subscription))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Subscriptions/Renewal')
            ->where('subscription.id', $this->subscription->id)
            ->where('subscription.student.name', $this->student->name));

    $response = $this->actingAs($this->parent)
        ->post(route('subscriptions.renewal.store', $this->subscription));

    $renewal = Subscription::query()
        ->whereKeyNot($this->subscription->id)
        ->where('student_id', $this->student->id)
        ->firstOrFail();

    $response->assertRedirect(route('checkout.show', $renewal));

    app(SubscriptionService::class)->renew($this->subscription->fresh());

    expect(Subscription::where('student_id', $this->student->id)->count())->toBe(2)
        ->and($renewal->status)->toBe(Subscription::STATUS_PENDING);
});

it('rejects an unrelated parent from the renewal flow', function () {
    $otherParent = User::factory()->create(['email_verified_at' => now()]);
    $otherParent->assignRole('parent');

    $this->actingAs($otherParent)
        ->get(route('subscriptions.renewal.show', $this->subscription))
        ->assertForbidden();

    $this->actingAs($otherParent)
        ->post(route('subscriptions.renewal.store', $this->subscription))
        ->assertForbidden();
});

it('protects the Vercel cron endpoint with CRON_SECRET', function () {
    Notification::fake();
    config()->set('services.vercel.cron_secret', 'test-cron-secret');

    $this->getJson(route('cron.subscription-renewal-reminders'))
        ->assertUnauthorized();

    $this->withHeader('Authorization', 'Bearer test-cron-secret')
        ->getJson(route('cron.subscription-renewal-reminders'))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'notifications_sent' => 1,
        ]);
});
