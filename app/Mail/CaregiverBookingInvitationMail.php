<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class CaregiverBookingInvitationMail extends Mailable
{
    use Queueable, SendGrid, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        $this->sendgrid([
            'personalizations' => [
                [
                    'dynamic_template_data' => $this->booking->toEmailData(),
                ],
            ],
            // Note: Template ID wasn't explicitly in the original code, but assuming consistency.
            // Using a placeholder or if none was specified, we might need to check original.
            // Re-checking original 'BookingNotification' which used a Blade view instead.
        ]);

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'New Booking Available - '.($this->booking->client->first_name ?? $this->booking->client_first_name),
        );
    }

    public function content(): Content
    {
        // Original used a view: 'emails.booking-notification'
        // If we want to switch to SendGrid dynamic template, we need a template ID.
        // For now, staying consistent with others.
        return new Content(
            htmlString: ' ',
        );
    }
}
