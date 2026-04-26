<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class CaregiverBookingAcceptedMail extends Mailable
{
    use Queueable, SendGrid, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Booking $booking) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $this->sendgrid([
            'personalizations' => [
                [
                    'dynamic_template_data' => $this->booking->toEmailData(),
                ],
            ],
            'template_id' => 'd-3f3ed05c7e5f4c40bcdffbc967ef8bdb',
        ]);

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Job Assignment Confirmed',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: ' ',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
