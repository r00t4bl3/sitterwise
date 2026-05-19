<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminPaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public int $attemptCount,
        public string $errorMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Payment Failed - Booking #'.$this->booking->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-payment-failed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
