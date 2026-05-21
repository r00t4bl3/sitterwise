<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferenceCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $referenceName,
        public string $applicantName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reference Completed — {$this->referenceName} for {$this->applicantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reference-completed',
        );
    }
}
