<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Application\Learning\Services\TeacherPerformanceAnalyticsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerformanceAnalyticsController extends Controller
{
    public function index(Request $request, TeacherPerformanceAnalyticsService $analytics): Response
    {
        $result = $analytics->forTeacher(Auth::user(), ...$this->filters($request));

        return Inertia::render('Teacher/PerformanceAnalytics', $result);
    }

    public function export(Request $request, TeacherPerformanceAnalyticsService $analytics): StreamedResponse
    {
        $filters = $this->filters($request);
        $result = $analytics->forTeacher(Auth::user(), ...$filters);
        $assignmentId = $filters[0];
        $filename = $assignmentId === null
            ? 'teacher-analytics.csv'
            : 'teacher-analytics-assignment-'.$assignmentId.'.csv';

        return response()->streamDownload(function () use ($result): void {
            $handle = fopen('php://output', 'wb');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Student',
                'Email',
                'Subjects',
                'Groups',
                'Lessons Completed',
                'Study Minutes',
                'Assignments Submitted',
                'Missing Assignments',
                'Quiz Attempts',
                'Average Quiz Score',
                'Attendance Rate',
                'Requires Attention',
                'Attention Reasons',
            ]);

            foreach ($result['rows'] as $row) {
                fputcsv($handle, [
                    $this->csvCell($row['name'] ?? null),
                    $this->csvCell($row['email'] ?? null),
                    $this->csvCell(implode('، ', $row['subjects'] ?? [])),
                    $this->csvCell(implode('، ', $row['groups'] ?? [])),
                    $row['lessons_completed'],
                    $row['study_minutes'],
                    $row['assignments_submitted'],
                    $row['missing_assignments'],
                    $row['quiz_attempts'],
                    $row['average_quiz_score'],
                    $row['attendance_rate'],
                    $row['requires_attention'] ? 'Yes' : 'No',
                    $this->csvCell(implode('، ', array_map(
                        fn (array $reason): string => (string) ($reason['code'] ?? ''),
                        $row['attention_reasons'] ?? [],
                    ))),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @return array{0: ?int, 1: ?int, 2: ?int, 3: int} */
    private function filters(Request $request): array
    {
        return [
            $request->integer('assignment') ?: null,
            $request->integer('group') ?: null,
            $request->integer('student') ?: null,
            $request->integer('days') ?: TeacherPerformanceAnalyticsService::DEFAULT_DAYS,
        ];
    }

    private function csvCell(mixed $value): string
    {
        $value = $value === null ? '' : (string) $value;

        return preg_match('/^\s*[=+\-@]/u', $value) === 1
            ? "'{$value}"
            : $value;
    }
}
