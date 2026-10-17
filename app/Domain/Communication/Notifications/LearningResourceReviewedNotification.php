<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Learning\Models\LearningResource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LearningResourceReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LearningResource $resource,
        public string $action, // 'approved' or 'rejected'
        public ?string $adminNotes = null,
        public ?string $rejectionReason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $isApproved = $this->action === 'approved';

        if ($isApproved) {
            $title = 'تم اعتماد المورد التعليمي';
            $message = 'تمت مراجعة واعتماد المورد "' . $this->resource->title . '" من قبل الإدارة.';
            if (filled($this->adminNotes)) {
                $message .= ' ملاحظات الإدارة: ' . $this->adminNotes;
            }
            $icon = '📚';
        } else {
            $title = 'ملاحظات حول المورد التعليمي';
            $message = 'تم رفض المورد "' . $this->resource->title . '".';
            if (filled($this->rejectionReason)) {
                $message .= ' سبب الرفض: ' . $this->rejectionReason . '.';
            }
            if (filled($this->adminNotes)) {
                $message .= ' المقترحات: ' . $this->adminNotes;
            }
            $icon = '⚠️';
        }

        return [
            'title' => $title,
            'message' => $message,
            'link' => route('teacher.resource-library'),
            'resource_id' => $this->resource->id,
            'action' => $this->action,
            'icon' => $icon,
        ];
    }
}
