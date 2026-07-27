<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Learning\Models\LiveSessionApology;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TeacherDeductionRecordedNotification extends Notification
{
    use Queueable;

    public function __construct(public LiveSessionApology $apology) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'تم تسجيل خصم على حصة معتذر عنها',
            'message' => 'سجلت الإدارة خصمًا بقيمة '.number_format($this->apology->deduction_amount / 100, 2).' ر.ق. على حصة '.$this->apology->session?->title.'.',
            'link' => route('teacher.live-sessions'),
            'apology_id' => $this->apology->id,
            'icon' => '⚠️',
        ];
    }
}
