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

class CaregiverBookingReminderMail extends Mailable
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
            'template_id' => 'd-c141f95e479746dd8af8d96aa1c64067',
        ]);

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Upcoming Job Reminder',
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
