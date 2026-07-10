<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Course\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the student after a successful enrollment.
 * Queued — never blocks the request cycle.
 */
class CourseEnrolledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Course $course,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("🎓 تم تسجيلك في كورس: {$this->course->title}")
            ->greeting("أهلاً {$notifiable->name}!")
            ->line("تم تسجيلك بنجاح في كورس **{$this->course->title}**.")
            ->action('ابدأ التعلم الآن', route('student.learn', ['slug' => $this->course->slug]))
            ->line('نتمنى لك رحلة تعلم ممتعة ومثمرة! 🚀')
            ->salutation('فريق منصة التفوق');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'enrollment',
            'title'      => "تم تسجيلك في: {$this->course->title}",
            'message'    => 'ابدأ تعلمك الآن وحقق التفوق!',
            'course_id'  => $this->course->id,
            'course_slug'=> $this->course->slug,
            'icon'       => '🎓',
        ];
    }
}
