<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Course\Models\Course;
use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(public Course $course, public User $student, public int $amountHalala)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $qar = number_format($this->amountHalala / 100, 2);
        return [
            'title'   => 'مدفوعات جديدة 💰',
            'message' => "تم تحصيل مبلغ {$qar} ر.ق. من الطالب '{$this->student->name}' للاشتراك في '{$this->course->title}'.",
            'link'    => route('admin.payments'),
        ];
    }
}
