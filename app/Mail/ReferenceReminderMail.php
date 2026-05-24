<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferenceReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $referenceName,
        public string $applicantName,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: "Reminder: Reference request for {$this->applicantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reference-reminder',
        );
    }
}
