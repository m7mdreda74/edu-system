<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AcademicAlertNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly array $data) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_filter([
            'type' => 'academic_alert',
            'alert_type' => $this->data['alert_type'] ?? null,
            'alert_key' => $this->data['alert_key'] ?? null,
            'title' => $this->data['title'] ?? 'تنبيه أكاديمي',
            'message' => $this->data['message'] ?? '',
            'link' => $this->data['link'] ?? route('dashboard'),
            'icon' => $this->data['icon'] ?? 'bell',
            'student_id' => $this->data['student_id'] ?? null,
            'entity_id' => $this->data['entity_id'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
