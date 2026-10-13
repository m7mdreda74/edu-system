<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cron;

use App\Application\Learning\Services\AcademicAlertService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AcademicAlertController extends Controller
{
    public function __invoke(AcademicAlertService $alerts): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$alerts->send(),
        ]);
    }
}
