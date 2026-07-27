<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];

        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (Throwable $exception) {
            $checks['database'] = 'error: '.$exception->getMessage();
        }

        try {
            Cache::put('health_check', true, 5);
            $checks['cache'] = Cache::get('health_check') ? 'ok' : 'error';
        } catch (Throwable $exception) {
            $checks['cache'] = 'error: '.$exception->getMessage();
        }

        return response()->json([
            'status' => 'ok',
            'checks' => $checks,
            'version' => Application::VERSION,
            'time' => now()->toIso8601String(),
        ]);
    }
}
