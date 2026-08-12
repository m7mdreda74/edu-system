<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cron;

use App\Application\Subscription\Services\SubscriptionRenewalReminderService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SubscriptionRenewalReminderController extends Controller
{
    public function __invoke(
        SubscriptionRenewalReminderService $reminders,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'notifications_sent' => $reminders->sendDueReminders(),
        ]);
    }
}
