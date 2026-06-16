<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CaregiverBookingInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'New Booking Available - '.($this->booking->client?->first_name ?? $this->booking->client_first_name),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-notification',
        );
    }
}
