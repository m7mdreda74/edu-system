<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cron;

use App\Application\Learning\Services\LiveSessionReminderService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class LiveSessionReminderController extends Controller
{
    public function __invoke(LiveSessionReminderService $reminders): JsonResponse
    {
        $due24h = $reminders->sendDueReminders();
        $due30m = $reminders->sendThirtyMinuteReminders();

        return response()->json([
            'success' => true,
            'notifications_sent' => $due24h + $due30m,
            'due_24h_sent' => $due24h,
            'due_30m_sent' => $due30m,
        ]);
    }
}
