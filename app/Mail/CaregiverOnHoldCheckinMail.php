<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CaregiverOnHoldCheckinMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $caregiverName,
        public int $daysOnHold,
        public string $tier,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->tier) {
            'final' => 'Action needed: Your Sitterwise account will be archived soon',
            'reminder' => "It's been {$this->daysOnHold} days — ready to come back?",
            default => 'Haven\'t seen you in a while — checking in!',
        };

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.caregiver-on-hold-checkin',
        );
    }
}
