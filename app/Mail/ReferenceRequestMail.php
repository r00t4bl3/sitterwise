<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferenceRequestMail extends Mailable
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
            subject: "Sitterwise Reference Request — {$this->applicantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reference-request',
        );
    }
}
