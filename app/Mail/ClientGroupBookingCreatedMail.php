<?php

namespace App\Mail;

use App\Models\BookingGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientGroupBookingCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BookingGroup $bookingGroup) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Group Booking Request Received',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-group-booking-created',
        );
    }
}
