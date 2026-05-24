<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicantPendingReferencesMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $applicantName,
        public int $daysSinceSubmission,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->daysSinceSubmission >= 7
            ? 'Still waiting on your references — Sitterwise'
            : 'Your Sitterwise references are still pending';

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.applicant-pending-references',
        );
    }
}
