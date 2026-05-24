<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicantFinalReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Last chance — finish your Sitterwise application',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.final-reminder',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
