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
        $filters = $request->only(['status', 'type', 'search']);

        $subscriptions = Subscription::with([
            'student:id,name,email',
            'assignment.subject:id,name',
            'assignment.teacher:id,name',
            'group:id,name',
        ])
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when(! empty($filters['search']), fn ($q) => $q->whereHas(
                'student',
                fn ($s) => $s->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%'),
            ))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Subscriptions', [
            'subscriptions' => $subscriptions,
            'filters'       => $filters,
            'stats'         => [
                'active'  => Subscription::active()->count(),
                'pending' => Subscription::where('status', Subscription::STATUS_PENDING)->count(),
                'expired' => Subscription::where('status', Subscription::STATUS_EXPIRED)->count(),
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
