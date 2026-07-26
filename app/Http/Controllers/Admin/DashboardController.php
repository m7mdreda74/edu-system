<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Models\Payment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
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
            $dateExpressions = DB::connection()->getDriverName() === 'sqlite'
                ? ["strftime('%Y', paid_at) as year", "strftime('%m', paid_at) as month"]
                : ['YEAR(paid_at) as year', 'MONTH(paid_at) as month'];

            $revenueChart = Payment::where('status', Payment::STATUS_PAID)
                ->where('paid_at', '>=', now()->subMonths(6))
                ->select(
                    DB::raw($dateExpressions[0]),
                    DB::raw($dateExpressions[1]),
                    DB::raw('SUM(amount) as total')
                )
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(fn ($r) => [
                    'label'  => now()->setYear((int) $r->year)->setMonth((int) $r->month)->format('M Y'),
                    'amount' => $r->total,
                ]);

            return [
                'total_users'           => User::count(),
                'total_students'        => User::role('student')->count(),
                'total_teachers'        => User::role('teacher')->count(),
                'total_groups'          => TeachingGroup::where('is_active', true)->count(),
                'active_subscriptions'  => Subscription::active()->count(),
                'private_subscriptions' => Subscription::active()->where('type', Subscription::TYPE_PRIVATE)->count(),
                'total_revenue'         => $totalRevenue,
                'monthly_revenue'       => $thisMonth,
                'revenue_chart'         => $revenueChart,
            ];
        });

        $recentPayments = Payment::with([
            'user:id,name',
            'subscription:id,teaching_assignment_id,teaching_group_id,type',
            'subscription.assignment.subject:id,name',
            'subscription.group:id,name',
        ])
            ->where('status', Payment::STATUS_PAID)
            ->latest('paid_at')
            ->limit(10)
            ->get(['id', 'user_id', 'subscription_id', 'amount', 'currency', 'gateway', 'paid_at']);

        return Inertia::render('Admin/Dashboard', [
            'stats'          => $stats,
            'recentPayments' => $recentPayments,
        ]);
    }
}
