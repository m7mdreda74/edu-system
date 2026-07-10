<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Course\Models\Course;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        // Cache platform stats for 10 minutes
        $stats = Cache::remember('admin_platform_stats', 600, function () {
            $totalRevenue = Payment::where('status', Payment::STATUS_PAID)->sum('amount');
            $thisMonth    = Payment::where('status', Payment::STATUS_PAID)
                ->whereMonth('paid_at', now()->month)
                ->sum('amount');

            // Revenue last 6 months for chart
            $revenueChart = Payment::where('status', Payment::STATUS_PAID)
                ->where('paid_at', '>=', now()->subMonths(6))
                ->select(
                    DB::raw('YEAR(paid_at) as year'),
                    DB::raw('MONTH(paid_at) as month'),
                    DB::raw('SUM(amount) as total')
                )
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(fn($r) => [
                    'label'  => now()->setYear($r->year)->setMonth($r->month)->format('M Y'),
                    'amount' => $r->total,
                ]);

            return [
                'total_users'       => User::count(),
                'total_students'    => User::role('student')->count(),
                'total_teachers'    => User::role('teacher')->count(),
                'total_courses'     => Course::where('is_published', true)->count(),
                'total_enrollments' => Enrollment::count(),
                'total_revenue'     => $totalRevenue,
                'monthly_revenue'   => $thisMonth,
                'revenue_chart'     => $revenueChart,
            ];
        });

        // Recent payments
        $recentPayments = Payment::with(['user:id,name', 'course:id,title'])
            ->where('status', Payment::STATUS_PAID)
            ->latest('paid_at')
            ->limit(10)
            ->get(['id', 'user_id', 'course_id', 'amount', 'currency', 'gateway', 'paid_at']);

        return Inertia::render('Admin/Dashboard', [
            'stats'          => $stats,
            'recentPayments' => $recentPayments,
        ]);
    }
}
