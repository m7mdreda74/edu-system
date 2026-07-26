<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\TeacherReview;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\TeacherPayout;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\PurchaseRequest;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin's overview.
 *
 * Numbers here are read live rather than cached: the page polls `stats` every
 * few seconds, and a dashboard that lags five minutes behind the till is worse
 * than no dashboard. The heavier six-month revenue series is separate, since it
 * barely moves within a session.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats'          => $this->liveStats(),
            'revenueChart'   => $this->revenueChart(),
            'recentPayments' => $this->recentPayments(),
            'recentActivity' => $this->recentActivity(),
            'term'           => $this->currentTerm(),
        ]);
    }

    /** Polled by the dashboard so the numbers stay current without a reload. */
    public function stats(): JsonResponse
    {
        return response()->json([
            'stats'     => $this->liveStats(),
            'fetchedAt' => now()->toIso8601String(),
        ]);
    }

    // ─── Data ─────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function liveStats(): array
    {
        $paid = Payment::where('status', Payment::STATUS_PAID);

        return [
            // People
            'students' => User::role('student')->where('is_active', true)->count(),
            'teachers' => User::role('teacher')->where('is_active', true)->count(),
            'parents'  => User::role('parent')->count(),

            // Teaching
            'groups'         => TeachingGroup::where('is_active', true)->count(),
            'live_now'       => LiveSession::where('status', LiveSession::STATUS_LIVE)->count(),
            'sessions_today' => LiveSession::whereDate('scheduled_at', today())->count(),

            // Money — amounts stay in the smallest unit; the client formats.
            'revenue_total'   => (int) (clone $paid)->sum('amount'),
            'revenue_month'   => (int) (clone $paid)->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount'),
            'revenue_today'   => (int) (clone $paid)->whereDate('paid_at', today())->sum('amount'),
            'platform_cut'    => (int) (clone $paid)->sum('platform_commission_amount'),

            // Subscriptions
            'subs_active'  => Subscription::active()->count(),
            'subs_pending' => Subscription::where('status', Subscription::STATUS_PENDING)->count(),
            'mrr'          => (int) Subscription::active()->sum('monthly_price'),

            // Anything sitting in a queue waiting on the admin.
            'needs_action' => [
                'payment_receipts'  => Payment::where('status', Payment::STATUS_PENDING_VERIFICATION)->count(),
                'pending_reviews'   => TeacherReview::where('is_approved', false)->count(),
                'pending_payouts'   => TeacherPayout::where('status', 'pending')->count(),
                'purchase_requests' => PurchaseRequest::where('status', PurchaseRequest::STATUS_PENDING)->count(),
                'empty_groups'      => TeachingGroup::where('is_active', true)->doesntHave('activeBookings')->count(),
                'teachers_no_video' => User::role('teacher')->where('is_active', true)->whereNull('intro_video_url')->count(),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function revenueChart(): array
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        $year  = $isSqlite ? "strftime('%Y', paid_at)" : 'YEAR(paid_at)';
        $month = $isSqlite ? "strftime('%m', paid_at)" : 'MONTH(paid_at)';

        return Payment::where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("{$year} as y, {$month} as m, SUM(amount) as total, COUNT(*) as payments")
            ->groupBy('y', 'm')
            ->orderBy('y')
            ->orderBy('m')
            ->get()
            ->map(fn ($row) => [
                'label'    => now()->setYear((int) $row->y)->setMonth((int) $row->m)->translatedFormat('M Y'),
                'amount'   => (int) $row->total,
                'payments' => (int) $row->payments,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function recentPayments(): array
    {
        return Payment::with([
            'user:id,name,avatar',
            'subscription.assignment.subject:id,name',
            'subscription.assignment.teacher:id,name',
        ])
            ->where('status', Payment::STATUS_PAID)
            ->latest('paid_at')
            ->limit(8)
            ->get()
            ->map(fn (Payment $payment) => [
                'id'      => $payment->id,
                'student' => $payment->user?->only(['id', 'name', 'avatar']),
                'subject' => $payment->subscription?->assignment?->subject?->name,
                'teacher' => $payment->subscription?->assignment?->teacher?->name,
                'amount'  => $payment->amount,
                'gateway' => $payment->gateway,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * A single stream of what just happened, so the admin can see the platform
     * moving rather than reading four separate tables.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(): array
    {
        $subscriptions = Subscription::with(['student:id,name', 'assignment.subject:id,name'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Subscription $s) => [
                'type'  => 'subscription',
                'icon'  => 'student',
                'text'  => ($s->student?->name ?? 'طالب') . ' اشترك في ' . ($s->assignment?->subject?->name ?? 'مادة'),
                'at'    => $s->created_at?->toIso8601String(),
                'badge' => $s->status,
            ]);

        $reviews = TeacherReview::with(['user:id,name', 'teacher:id,name'])
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (TeacherReview $r) => [
                'type'  => 'review',
                'icon'  => 'chat',
                'text'  => ($r->user?->name ?? 'طالب') . ' قيّم ' . ($r->teacher?->name ?? 'معلماً') . " بـ {$r->rating} نجوم",
                'at'    => $r->created_at?->toIso8601String(),
                'badge' => $r->is_approved ? 'approved' : 'pending',
            ]);

        return collect()
            ->merge($subscriptions)
            ->merge($reviews)
            ->sortByDesc('at')
            ->take(10)
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function currentTerm(): ?array
    {
        $term = AcademicTerm::currentOrNext();

        if (! $term) {
            return null;
        }

        return [
            'name'            => $term->fullName(),
            'starts_on'       => $term->starts_on?->toDateString(),
            'ends_on'         => $term->ends_on?->toDateString(),
            'is_current'      => $term->isCurrent(),
            'is_provisional'  => $term->is_provisional,
            'weeks_remaining' => $term->weeksRemaining(),
        ];
    }
}
