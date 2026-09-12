<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cron;

use App\Application\Learning\Services\MissedLiveSessionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MissedLiveSessionCronController extends Controller
{
    public function __invoke(MissedLiveSessionService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'sessions_cancelled' => $service->cancelOverdueSessions(),
        ]);
    }
}
