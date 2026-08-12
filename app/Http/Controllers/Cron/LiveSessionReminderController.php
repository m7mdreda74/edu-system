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
        return response()->json([
            'success' => true,
            'notifications_sent' => $reminders->sendDueReminders(),
        ]);
    }
}
