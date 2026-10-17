<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Learning\Models\LiveSessionApology;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SessionApologySubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LiveSessionApology $apology) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $sessionTitle = trim((string) ($this->apology->session?->title ?? ''));
        $formattedTitle = preg_match('/^حصة\b/u', $sessionTitle) ? $sessionTitle : 'حصة '.$sessionTitle;

        return [
            'title' => 'اعتذار جديد عن الحصة',
            'message' => $this->apology->teacher?->name.' اعتذر عن '.$formattedTitle.'.',
            'link' => route('admin.session-apologies'),
            'apology_id' => $this->apology->id,
            'icon' => '📅',
        ];
    }
}
