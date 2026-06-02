<?php

namespace App\Mail;

use App\Models\BookingGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminGroupBookingCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BookingGroup $bookingGroup) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'New Group Booking Created',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-group-booking-created',
        );
    }
}
