<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Course\Models\Course;
use App\Domain\Enrollment\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the student upon 100% course completion.
 * Triggers certificate generation flow.
 */
class CourseCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Course     $course,
        private readonly Enrollment $enrollment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("🏆 أكملت كورس: {$this->course->title}!")
            ->greeting("تهانينا {$notifiable->name}! 🎉")
            ->line("أتممت بنجاح كورس **{$this->course->title}**.")
            ->line('شهادتك جاهزة للتحميل الآن!')
            ->action('تحميل الشهادة', route('student.certificate', ['enrollmentId' => $this->enrollment->id]))
            ->salutation('فريق منصة التفوق');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'course_completed',
            'title'         => "🏆 أتممت كورس: {$this->course->title}",
            'message'       => 'شهادتك جاهزة! احتفل بإنجازك.',
            'course_id'     => $this->course->id,
            'enrollment_id' => $this->enrollment->id,
            'icon'          => '🏆',
        ];
    }
}
