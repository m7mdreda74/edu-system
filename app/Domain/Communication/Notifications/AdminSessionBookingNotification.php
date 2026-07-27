<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Scheduling\Models\SessionBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminSessionBookingNotification extends Notification
{
    use Queueable;

    public function __construct(public SessionBooking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $assignment = $this->booking->group?->assignment ?? $this->booking->privateSlot?->assignment;
        $student = $this->booking->student?->name ?? 'طالب';
        $subject = $assignment?->subject?->name ?? 'مادة دراسية';
        $teacher = $assignment?->teacher?->name ?? 'مدرس';
        $group = $this->booking->group?->name ?? 'حصة برايفيت';

        return [
            'title' => 'حجز طالب جديد',
            'message' => "قام الطالب {$student} بحجز مادة {$subject} مع المدرس {$teacher} في {$group}.",
            'link' => route('admin.reports'),
            'booking_id' => $this->booking->id,
            'student_id' => $this->booking->student_id,
            'teacher_id' => $assignment?->teacher_id,
            'subject_id' => $assignment?->subject_id,
            'group_id' => $this->booking->teaching_group_id,
            'icon' => '🎟️',
        ];
    }
}
