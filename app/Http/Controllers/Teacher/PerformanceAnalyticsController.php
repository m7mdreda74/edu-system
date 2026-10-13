<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Application\Learning\Services\TeacherPerformanceAnalyticsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceAnalyticsController extends Controller
{
    public function index(Request $request, TeacherPerformanceAnalyticsService $analytics): Response
    {
        $days = $request->integer('days') ?: TeacherPerformanceAnalyticsService::DEFAULT_DAYS;
        $assignmentId = $request->integer('assignment') ?: null;
        $groupId = $request->integer('group') ?: null;
        $studentId = $request->integer('student') ?: null;

        $result = $analytics->forTeacher(
            Auth::user(),
            $assignmentId,
            $groupId,
            $studentId,
            $days,
        );

        return Inertia::render('Teacher/PerformanceAnalytics', $result);
    }
}
