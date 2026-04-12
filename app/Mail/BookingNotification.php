<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Caregiver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Caregiver $caregiver
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Booking Available - '.$this->booking->client->first_name.' '.$this->booking->client->last_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-notification',
        );
    }
}
