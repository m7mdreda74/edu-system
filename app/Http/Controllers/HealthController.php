<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $healthy = true;
        $checks  = [];

        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (Throwable) {
            $healthy = false;
            $checks['database'] = 'error';
        }

        try {
            Cache::put('health_check', true, 5);
            $checks['cache'] = Cache::get('health_check') ? 'ok' : 'error';
        } catch (Throwable) {
            $healthy = false;
            $checks['cache'] = 'error';
        }

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'time' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
