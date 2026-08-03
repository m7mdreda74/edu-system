<?php

use App\Application\Learning\Services\LiveSessionReminderService;
use App\Application\Subscription\Services\SubscriptionRenewalReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'subscriptions:send-renewal-reminders',
    function (SubscriptionRenewalReminderService $reminders): void {
        $count = $reminders->sendDueReminders();

        $this->info("Sent {$count} subscription renewal reminder(s).");
    },
)->purpose('Notify students and parents before the final class in a subscription period');

Artisan::command(
    'sessions:send-reminders',
    function (LiveSessionReminderService $reminders): void {
        $count = $reminders->sendDueReminders();

        $this->info("Sent {$count} live session reminder(s).");
    },
)->purpose('Notify students up to 24 hours before every scheduled class');
