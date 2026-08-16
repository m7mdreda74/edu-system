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
        public readonly int $completedLessons = 7,
        public readonly int $lessonsPerMonth = 8,
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
            'title' => 'حان موعد تجديد الاشتراك 🔔',
            'message' => "انتهت الحصة رقم {$this->completedLessons} من أصل {$this->lessonsPerMonth} في اشتراك {$this->subscription->label()} يوم {$lessonAt}. يرجى تجديد الاشتراك والدفع للحصص القادمة.",
            'link' => route('subscriptions.renewal.show', $this->subscription),
            'subscription_id' => $this->subscription->id,
            'last_lesson_at' => $this->lastLessonAt->toIso8601String(),
            'completed_lessons' => $this->completedLessons,
            'lessons_per_month' => $this->lessonsPerMonth,
            'icon' => 'calendar',
        ];
    }
}
