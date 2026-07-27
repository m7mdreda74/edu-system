<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Learning\Models\TeacherReview;
use App\Domain\Payment\Models\Payment;
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
 * Live figures are read in one database round trip and refreshed once per
 * minute. The heavier six-month revenue series is separate, since it barely
 * moves within a session.
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
        $today = today();
        $row = DB::selectOne(
            <<<'SQL'
                SELECT
                    (SELECT COUNT(*) FROM users u
                        WHERE u.is_active = 1 AND EXISTS (
                            SELECT 1 FROM model_has_roles mhr
                            INNER JOIN roles r ON r.id = mhr.role_id
                            WHERE mhr.model_id = u.id AND mhr.model_type = ? AND r.name = 'student'
                        )) AS students,
                    (SELECT COUNT(*) FROM users u
                        WHERE u.is_active = 1 AND EXISTS (
                            SELECT 1 FROM model_has_roles mhr
                            INNER JOIN roles r ON r.id = mhr.role_id
                            WHERE mhr.model_id = u.id AND mhr.model_type = ? AND r.name = 'teacher'
                        )) AS teachers,
                    (SELECT COUNT(*) FROM users u
                        WHERE EXISTS (
                            SELECT 1 FROM model_has_roles mhr
                            INNER JOIN roles r ON r.id = mhr.role_id
                            WHERE mhr.model_id = u.id AND mhr.model_type = ? AND r.name = 'parent'
                        )) AS parents,
                    (SELECT COUNT(*) FROM teaching_groups WHERE is_active = 1) AS groups_count,
                    (SELECT COUNT(*) FROM live_sessions WHERE status = 'live') AS live_now,
                    (SELECT COUNT(*) FROM live_sessions WHERE scheduled_at >= ? AND scheduled_at < ?) AS sessions_today,
                    (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid') AS revenue_total,
                    (SELECT COALESCE(SUM(amount), 0) FROM payments
                        WHERE status = 'paid' AND paid_at >= ? AND paid_at < ?) AS revenue_month,
                    (SELECT COALESCE(SUM(amount), 0) FROM payments
                        WHERE status = 'paid' AND paid_at >= ? AND paid_at < ?) AS revenue_today,
                    (SELECT COALESCE(SUM(platform_commission_amount), 0) FROM payments WHERE status = 'paid') AS platform_cut,
                    (SELECT COUNT(*) FROM subscriptions WHERE status = 'active' AND period_end >= ?) AS subs_active,
                    (SELECT COUNT(*) FROM subscriptions WHERE status = 'pending') AS subs_pending,
                    (SELECT COALESCE(SUM(monthly_price), 0) FROM subscriptions
                        WHERE status = 'active' AND period_end >= ?) AS mrr,
                    (SELECT COUNT(*) FROM payments WHERE status = 'pending_verification') AS payment_receipts,
                    (SELECT COUNT(*) FROM reviews WHERE is_approved = 0) AS pending_reviews,
                    (SELECT COUNT(*) FROM teacher_payouts WHERE status = 'pending') AS pending_payouts,
                    (SELECT COUNT(*) FROM purchase_requests WHERE status = 'pending') AS purchase_requests,
                    (SELECT COUNT(*) FROM teaching_groups tg
                        WHERE tg.is_active = 1 AND NOT EXISTS (
                            SELECT 1 FROM session_bookings sb
                            WHERE sb.teaching_group_id = tg.id AND sb.status = 'confirmed'
                        )) AS empty_groups,
                    (SELECT COUNT(*) FROM users u
                        WHERE u.is_active = 1 AND u.intro_video_url IS NULL AND EXISTS (
                            SELECT 1 FROM model_has_roles mhr
                            INNER JOIN roles r ON r.id = mhr.role_id
                            WHERE mhr.model_id = u.id AND mhr.model_type = ? AND r.name = 'teacher'
                        )) AS teachers_no_video
                SQL,
            [
                User::class,
                User::class,
                User::class,
                $today->copy()->startOfDay(),
                $today->copy()->addDay()->startOfDay(),
                $today->copy()->startOfMonth(),
                $today->copy()->addMonth()->startOfMonth(),
                $today->copy()->startOfDay(),
                $today->copy()->addDay()->startOfDay(),
                $today->toDateString(),
                $today->toDateString(),
                User::class,
            ],
        );

        return [
            'students'       => (int) $row->students,
            'teachers'       => (int) $row->teachers,
            'parents'        => (int) $row->parents,
            'groups'         => (int) $row->groups_count,
            'live_now'       => (int) $row->live_now,
            'sessions_today' => (int) $row->sessions_today,
            'revenue_total'  => (int) $row->revenue_total,
            'revenue_month'  => (int) $row->revenue_month,
            'revenue_today'  => (int) $row->revenue_today,
            'platform_cut'   => (int) $row->platform_cut,
            'subs_active'    => (int) $row->subs_active,
            'subs_pending'   => (int) $row->subs_pending,
            'mrr'            => (int) $row->mrr,
            'needs_action' => [
                'payment_receipts'  => (int) $row->payment_receipts,
                'pending_reviews'   => (int) $row->pending_reviews,
                'pending_payouts'   => (int) $row->pending_payouts,
                'purchase_requests' => (int) $row->purchase_requests,
                'empty_groups'      => (int) $row->empty_groups,
                'teachers_no_video' => (int) $row->teachers_no_video,
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
