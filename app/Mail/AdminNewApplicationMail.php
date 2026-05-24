<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $applicantName,
        public string $applicantEmail,
        public int $applicationId,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: "New Caregiver Application — {$this->applicantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-new-application',
        );
    }
}
