<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingReviewReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'How was your Sitterwise experience? Share your review!',
        );
    }

    public function content(): Content
    {
        $caregiverName = $this->booking->caregiver
            ? $this->booking->caregiver->first_name.' '.$this->booking->caregiver->last_name
            : 'your sitter';

        $date = $this->booking->end_datetime->setTimezone('America/Los_Angeles')->format('l, F j, Y');

        return new Content(
            view: 'emails.review-reminder',
            with: [
                'caregiverName' => $caregiverName,
                'date' => $date,
                'reviewUrl' => $this->reviewUrl,
            ],
        );
    }
}
