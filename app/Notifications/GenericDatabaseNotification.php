<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GenericDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(private array $data)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => $this->data['title'] ?? 'تنبيه جديد 🔔',
            'message' => $this->data['message'] ?? '',
            'link'    => $this->data['link'] ?? '#',
        ];
    }
}
