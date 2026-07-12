<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Communication\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewChatMessageNotification extends Notification
{
    use Queueable;

    public function __construct(public ChatMessage $chatMessage, public string $senderName)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => 'رسالة جديدة 💬',
            'message' => "لديك رسالة جديدة من {$this->senderName}: '" . mb_strimwidth($this->chatMessage->message ?? ($this->chatMessage->attachment_path ? '[ملف مرفق]' : ''), 0, 50, '...') . "'",
            'link'    => route('chat.index', ['conversation' => $this->chatMessage->conversation_id]),
        ];
    }
}
