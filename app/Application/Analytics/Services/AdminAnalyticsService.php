<?php

declare(strict_types=1);

namespace App\Application\Analytics\Services;

use App\Domain\Payment\Models\Payment;
use App\Domain\Subscription\Models\Subscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read-only, aggregated figures for the administrator's analytics screen.
 *
 * This intentionally computes from the current subscription model rather than
 * introducing a snapshot table before the reporting contract is proven. The
 * short cache keeps the page inexpensive while still reflecting operational
 * changes during the day.
 */
class AdminAnalyticsService
{
    public const CACHE_KEY = 'admin.analytics.v1';

    public const CACHE_TTL_MINUTES = 15;

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn (): array => [
                'generated_at' => now()->toIso8601String(),
                'cache_ttl_seconds' => self::CACHE_TTL_MINUTES * 60,
                'revenue' => $this->revenue(),
                'top_groups' => $this->topGroups(),
                'completion' => $this->completion(),
                'churn' => $this->churn(),
                'ratings' => $this->ratings(),
                'peak_sessions' => $this->peakSessions(),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function revenue(): array
    {
        $start = now()->startOfMonth()->subMonths(11);
        $driver = DB::connection()->getDriverName();
        $monthExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', paid_at)"
            : "DATE_FORMAT(paid_at, '%Y-%m')";

        $rows = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)
            ->selectRaw("{$monthExpression} as month_key, SUM(amount) as amount, COUNT(*) as payments")
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get()
            ->keyBy('month_key');

        $months = collect(range(0, 11))->map(function (int $offset) use ($start, $rows): array {
            $month = $start->copy()->addMonths($offset);
            $key = $month->format('Y-m');
            $row = $rows->get($key);

            return [
                'key' => $key,
                'label' => $month->format('M Y'),
                'amount' => (int) ($row?->amount ?? 0),
                'payments' => (int) ($row?->payments ?? 0),
            ];
        })->all();

        return [
            'months' => $months,
            'total' => (int) collect($months)->sum('amount'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function topGroups(): array
    {
        return DB::table('payments')
            ->join('subscriptions', 'subscriptions.id', '=', 'payments.subscription_id')
            ->join('teaching_groups', 'teaching_groups.id', '=', 'subscriptions.teaching_group_id')
            ->join('teaching_assignments', 'teaching_assignments.id', '=', 'subscriptions.teaching_assignment_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'teaching_assignments.subject_id')
            ->where('payments.status', Payment::STATUS_PAID)
            ->select([
                'teaching_groups.id',
                'teaching_groups.name',
                'subjects.name as subject_name',
            ])
            ->selectRaw('SUM(payments.amount) as revenue')
            ->selectRaw('COUNT(payments.id) as sales')
            ->groupBy('teaching_groups.id', 'teaching_groups.name', 'subjects.name')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'subject' => $row->subject_name,
                'revenue' => (int) $row->revenue,
                'sales' => (int) $row->sales,
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function completion(): array
    {
        $row = DB::table('lesson_progress')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed')
            ->first();

        $total = (int) ($row->total ?? 0);
        $completed = (int) ($row->completed ?? 0);

        return [
            'total' => $total,
            'completed' => $completed,
            'rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function churn(): array
    {
        $paidStatuses = [
            Subscription::STATUS_ACTIVE,
            Subscription::STATUS_EXPIRED,
            Subscription::STATUS_CANCELLED,
        ];
        $cutoff = today()->subMonths(3)->toDateString();

        $firstPurchase = DB::table('subscriptions as first')
            ->whereIn('first.status', $paidStatuses)
            ->where('first.period_end', '<=', $cutoff)
            ->whereNotExists(function ($query) use ($paidStatuses): void {
                $query->selectRaw('1')
                    ->from('subscriptions as prior')
                    ->whereColumn('prior.student_id', 'first.student_id')
                    ->whereIn('prior.status', $paidStatuses)
                    ->whereColumn('prior.period_start', '<', 'first.period_start');
            });

        $eligible = (clone $firstPurchase)->distinct()->count('first.student_id');
        $churned = (clone $firstPurchase)
            ->whereNotExists(function ($query) use ($paidStatuses): void {
                $query->selectRaw('1')
                    ->from('subscriptions as later')
                    ->whereColumn('later.student_id', 'first.student_id')
                    ->whereIn('later.status', $paidStatuses)
                    ->whereColumn('later.period_start', '>', 'first.period_start');
            })
            ->distinct()
            ->count('first.student_id');

        return [
            'eligible_students' => (int) $eligible,
            'churned_students' => (int) $churned,
            'rate' => $eligible > 0 ? round(($churned / $eligible) * 100, 1) : 0,
            'window_months' => 3,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ratings(): array
    {
        return DB::table('reviews')
            ->join('users as teachers', 'teachers.id', '=', 'reviews.teacher_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'teachers.subject_id')
            ->where('reviews.is_approved', true)
            ->select([
                'reviews.teacher_id as teacher_id',
                'teachers.name as teacher_name',
                'subjects.name as subject_name',
            ])
            ->selectRaw('AVG(reviews.rating) as average_rating')
            ->selectRaw('COUNT(reviews.id) as reviews_count')
            ->groupBy('reviews.teacher_id', 'teachers.name', 'subjects.name')
            ->orderByDesc('average_rating')
            ->orderByDesc('reviews_count')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'teacher_id' => (int) $row->teacher_id,
                'teacher' => (string) $row->teacher_name,
                'subject' => $row->subject_name,
                'average' => round((float) $row->average_rating, 2),
                'reviews' => (int) $row->reviews_count,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function peakSessions(): array
    {
        $driver = DB::connection()->getDriverName();
        $dayExpression = $driver === 'sqlite'
            ? "strftime('%w', scheduled_at)"
            : '(DAYOFWEEK(scheduled_at) - 1)';
        $hourExpression = $driver === 'sqlite'
            ? "strftime('%H', scheduled_at)"
            : 'HOUR(scheduled_at)';

        return DB::table('live_sessions')
            ->whereNotNull('scheduled_at')
            ->selectRaw("{$dayExpression} as day_of_week, {$hourExpression} as hour, COUNT(*) as sessions")
            ->groupBy('day_of_week', 'hour')
            ->orderByDesc('sessions')
            ->orderBy('day_of_week')
            ->orderBy('hour')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'day_of_week' => (int) $row->day_of_week,
                'hour' => (int) $row->hour,
                'sessions' => (int) $row->sessions,
            ])
            ->all();
    }
}
