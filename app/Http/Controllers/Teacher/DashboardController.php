<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Course\Models\Course;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Payment\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $teacher = auth()->user();

        // Cache per-teacher dashboard data (5 min)
        $stats = Cache::remember("teacher_stats:{$teacher->id}", 300, function () use ($teacher) {
            $courseIds = $teacher->coursesAsTeacher()->pluck('id');

            $totalStudents = Enrollment::whereIn('course_id', $courseIds)->count();
            $totalRevenue  = Payment::whereIn('course_id', $courseIds)
                ->where('status', Payment::STATUS_PAID)
                ->sum('amount');

            $completedStudents = Enrollment::whereIn('course_id', $courseIds)
                ->where('progress_percent', 100)
                ->count();

            return [
                'total_courses'    => $courseIds->count(),
                'total_students'   => $totalStudents,
                'completed_students' => $completedStudents,
                'total_revenue'    => $totalRevenue, // in halala
            ];
        });

        // Recent enrollments (last 10) — N+1 safe
        $recentEnrollments = Enrollment::with([
            'user:id,name,avatar',
            'course:id,title,slug',
        ])
        ->whereIn('course_id', $teacher->coursesAsTeacher()->pluck('id'))
        ->latest()
        ->limit(10)
        ->get();

        // Course performance
        $courses = $teacher->coursesAsTeacher()
            ->with('subject:id,name')
            ->withCount('enrollments')
            ->withAvg('reviews as avg_rating', 'rating')
            ->latest()
            ->get([
                'id', 'subject_id', 'title', 'slug', 'thumbnail',
                'price', 'discount_price', 'is_published',
                'total_lessons', 'created_at',
            ]);

        return Inertia::render('Teacher/Dashboard', [
            'stats'             => $stats,
            'recentEnrollments' => $recentEnrollments,
            'courses'           => $courses,
        ]);
    }
}
