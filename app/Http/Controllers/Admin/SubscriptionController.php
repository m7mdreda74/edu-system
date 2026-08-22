<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Subscription\Models\Subscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin oversight of every monthly subscription on the platform.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,active,expired,cancelled'],
            'type' => ['nullable', 'string', 'in:group,private'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $today = now()->toDateString();

        $subscriptions = Subscription::with([
            'student:id,name,email',
            'assignment.subject:id,name',
            'assignment.teacher:id,name',
            'group:id,name',
        ])
            ->when(! empty($filters['status']), function ($query) use ($filters, $today): void {
                $status = $filters['status'];

                if ($status === Subscription::STATUS_ACTIVE) {
                    $query
                        ->where('status', Subscription::STATUS_ACTIVE)
                        ->whereNotNull('period_end')
                        ->whereDate('period_end', '>=', $today);

                    return;
                }

                if ($status === Subscription::STATUS_EXPIRED) {
                    $query->where(function ($expired) use ($today): void {
                        $expired->where('status', Subscription::STATUS_EXPIRED)
                            ->orWhere(function ($stale) use ($today): void {
                                $stale->where('status', Subscription::STATUS_ACTIVE)
                                    ->where(function ($period) use ($today): void {
                                        $period->whereNull('period_end')
                                            ->orWhereDate('period_end', '<', $today);
                                    });
                            });
                    });

                    return;
                }

                $query->where('status', $status);
            })
            ->when(! empty($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when(! empty($filters['search']), fn ($q) => $q->whereHas(
                'student',
                fn ($s) => $s->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%'),
            ))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $subscriptions->through(function (Subscription $subscription): array {
            return array_merge($subscription->toArray(), [
                'status' => $subscription->effectiveStatus(),
            ]);
        });

        return Inertia::render('Admin/Subscriptions', [
            'subscriptions' => $subscriptions,
            'filters'       => $filters,
            'stats'         => [
                'active'  => Subscription::active()->count(),
                'pending' => Subscription::where('status', Subscription::STATUS_PENDING)->count(),
                'expired' => Subscription::query()
                    ->where(function ($query) use ($today): void {
                        $query->where('status', Subscription::STATUS_EXPIRED)
                            ->orWhere(function ($stale) use ($today): void {
                                $stale->where('status', Subscription::STATUS_ACTIVE)
                                    ->where(function ($period) use ($today): void {
                                        $period->whereNull('period_end')
                                            ->orWhereDate('period_end', '<', $today);
                                    });
                            });
                    })
                    ->count(),
                'monthly_recurring_revenue' => Subscription::active()->sum('monthly_price'),
            ],
        ]);
    }

    public function cancel(int $id): RedirectResponse
    {
        $this->subscriptions->cancel(Subscription::findOrFail($id));

        return back()->with('success', 'تم إلغاء الاشتراك.');
    }
}

