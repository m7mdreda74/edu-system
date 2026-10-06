<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LessonVideoService;
use Illuminate\Http\JsonResponse;

final class VideoReconciliationController extends Controller
{
    public function __construct(
        private readonly LessonVideoService $videos,
    ) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'reconciled' => $this->videos->reconcilePending(),
        ]);
    }
}
