<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Course\Models\Course;
use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentEnrolledNotification extends Notification
{
    use Queueable;

    public function __construct(public Course $course, public User $student)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => 'اشتراك جديد 🎓',
            'message' => "اشترك الطالب '{$this->student->name}' في كورس '{$this->course->title}' الخاص بك.",
            'link'    => route('teacher.courses'), // Or a course details page for the teacher
        ];
    }
}
