<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cron;

use App\Application\Subscription\Services\SubscriptionRenewalReminderService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionRenewalReminderController extends Controller
{
    public function __invoke(
        Request $request,
        SubscriptionRenewalReminderService $reminders,
    ): JsonResponse {
        $secret = (string) config('services.vercel.cron_secret', '');
        $provided = (string) $request->header('Authorization', '');

        abort_if(
            $secret === '' || ! hash_equals('Bearer '.$secret, $provided),
            401,
            'Unauthorized',
        );

        return response()->json([
            'success' => true,
            'notifications_sent' => $reminders->sendDueReminders(),
        ]);
    }
}
