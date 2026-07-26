<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Subscription\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Confirms to the student that their month of access is live. */
class SubscriptionActivatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $until = $this->subscription->period_end?->format('Y-m-d');

        return [
            'type'            => 'subscription_activated',
            'title'           => 'تم تفعيل اشتراكك ✅',
            'message'         => "اشتراكك في {$this->subscription->label()} فعّال حتى {$until}.",
            'link'            => route('student.my-classes'),
            'subscription_id' => $this->subscription->id,
            'icon'            => '🎓',
        ];
    }
}
