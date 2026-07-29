<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Subscription\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SubscriptionRenewalReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly Carbon $lastLessonAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $lessonAt = $this->lastLessonAt->format('Y-m-d H:i');

        return [
            'type' => 'subscription_renewal_reminder',
            'title' => 'الحصة القادمة هي الأخيرة 🔔',
            'message' => "اشتراك {$this->subscription->label()} سينتهي قريبًا، والحصة القادمة يوم {$lessonAt} هي آخر حصة في الاشتراك الحالي. هل تود تجديد الاشتراك؟",
            'link' => route('subscriptions.renewal.show', $this->subscription),
            'subscription_id' => $this->subscription->id,
            'last_lesson_at' => $this->lastLessonAt->toIso8601String(),
            'icon' => 'calendar',
        ];
    }
}
