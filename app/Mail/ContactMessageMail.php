<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array{name: string, email: string, phone: string, message: string} $contact
     */
    public function __construct(public array $contact)
    {
    }

    public function build(): static
    {
        return $this
            ->subject('رسالة جديدة من صفحة تواصل معنا')
            ->view('emails.contact-message');
    }
}
