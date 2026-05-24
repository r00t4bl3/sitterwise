<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicantDeclinedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $applicantName,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Update on your Sitterwise application',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application-declined',
        );
    }
}
