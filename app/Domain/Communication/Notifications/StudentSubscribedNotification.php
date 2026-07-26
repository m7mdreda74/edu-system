<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Tells a teacher that a student subscribed to one of their groups. */
class StudentSubscribedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public User $student,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => 'اشتراك جديد 🎓',
            'message' => "اشترك الطالب '{$this->student->name}' في {$this->subscription->label()}.",
            'link'    => route('teacher.teaching-schedule'),
        ];
    }
}
