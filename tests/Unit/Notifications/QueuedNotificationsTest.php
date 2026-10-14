<?php

declare(strict_types=1);

use App\Domain\Communication\Notifications\AcademicAlertNotification;
use App\Domain\Communication\Notifications\AdminLiveSessionStatusNotification;
use App\Domain\Communication\Notifications\AdminSessionBookingNotification;
use App\Domain\Communication\Notifications\LiveSessionReminderNotification;
use App\Domain\Communication\Notifications\LiveSessionStartedNotification;
use App\Domain\Communication\Notifications\ManualPaymentSubmittedNotification;
use App\Domain\Communication\Notifications\NewChatMessageNotification;
use App\Domain\Communication\Notifications\PaymentReceivedNotification;
use App\Domain\Communication\Notifications\PayoutStatusNotification;
use App\Domain\Communication\Notifications\SessionApologySubmittedNotification;
use App\Domain\Communication\Notifications\SessionScheduleChangedNotification;
use App\Domain\Communication\Notifications\StudentLiveSessionActivityNotification;
use App\Domain\Communication\Notifications\StudentSubscribedNotification;
use App\Domain\Communication\Notifications\SubscriptionRenewalReminderNotification;
use App\Domain\Communication\Notifications\TeacherDeductionRecordedNotification;
use App\Notifications\GenericDatabaseNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentSuccessNotification;
use App\Notifications\SubscriptionActivatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

it('keeps every application notification on the queue boundary', function (string $notification): void {
    expect(is_subclass_of($notification, ShouldQueue::class))->toBeTrue();
})->with([
    GenericDatabaseNotification::class,
    PaymentRejectedNotification::class,
    PaymentSuccessNotification::class,
    SubscriptionActivatedNotification::class,
    AcademicAlertNotification::class,
    AdminLiveSessionStatusNotification::class,
    AdminSessionBookingNotification::class,
    LiveSessionReminderNotification::class,
    LiveSessionStartedNotification::class,
    ManualPaymentSubmittedNotification::class,
    NewChatMessageNotification::class,
    PaymentReceivedNotification::class,
    PayoutStatusNotification::class,
    SessionApologySubmittedNotification::class,
    SessionScheduleChangedNotification::class,
    StudentLiveSessionActivityNotification::class,
    StudentSubscribedNotification::class,
    SubscriptionRenewalReminderNotification::class,
    TeacherDeductionRecordedNotification::class,
]);
