<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Learning\Models\LiveSessionApology;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SessionApologySubmittedNotification extends Notification
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
            'title' => 'اعتذار جديد عن حصة',
            'message' => $this->apology->teacher?->name.' اعتذر عن حصة '.$this->apology->session?->title.'.',
            'link' => route('admin.session-apologies'),
            'apology_id' => $this->apology->id,
            'icon' => '📅',
        ];
    }
}
