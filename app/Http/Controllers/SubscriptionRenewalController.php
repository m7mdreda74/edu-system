<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionRenewalController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function show(Subscription $subscription): Response
    {
        $this->authorizeRenewal($subscription);
        $subscription->loadMissing([
            'student:id,name',
            'assignment.subject:id,name,icon',
            'assignment.teacher:id,name,avatar',
            'group:id,name',
        ]);

        return Inertia::render('Subscriptions/Renewal', [
            'subscription' => [
                'id' => $subscription->id,
                'label' => $subscription->label(),
                'period_end' => $subscription->period_end?->toDateString(),
                'monthly_price' => $subscription->monthly_price,
                'currency' => $subscription->currency,
                'student' => $subscription->student?->only(['id', 'name']),
                'subject' => $subscription->assignment?->subject?->only(['id', 'name', 'icon']),
                'teacher' => $subscription->assignment?->teacher?->only(['id', 'name', 'avatar']),
                'group' => $subscription->group?->only(['id', 'name']),
            ],
            'hasPendingRenewal' => $this->subscriptions->findExistingRenewal($subscription) !== null,
            'backUrl' => Auth::user()?->isParent()
                ? route('parent.dashboard')
                : route('student.my-classes'),
        ]);
    }

    public function store(Subscription $subscription): RedirectResponse
    {
        $this->authorizeRenewal($subscription);
        $renewal = $this->subscriptions->renew($subscription);

        return redirect()->route('checkout.show', $renewal);
    }

    private function authorizeRenewal(Subscription $subscription): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($subscription->student_id === $user->id) {
            return;
        }

        $isLinkedParent = ParentStudentLink::query()
            ->where('parent_user_id', $user->id)
            ->where('student_user_id', $subscription->student_id)
            ->whereNotNull('verified_at')
            ->exists();

        abort_unless($isLinkedParent, 403, 'غير مصرح لك بتجديد هذا الاشتراك.');
    }
}
