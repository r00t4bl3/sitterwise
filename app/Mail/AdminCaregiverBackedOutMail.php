<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminCaregiverBackedOutMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $caregiverName,
        public int $caregiverId,
        public int $bookingId,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: "Caregiver {$this->caregiverName} has backed out of job #{$this->bookingId}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-caregiver-backed-out',
        );
    }
}
