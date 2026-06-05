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

class SendGridTemplate extends Mailable
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

        $dynamic_template_data = $this->booking->toEmailData();

        $this->sendgrid([
            'personalizations' => [
                [
                    // 'dynamic_template_data' => $this->booking->toEmailData(),
                    'dynamic_template_data' => [
                        'service_date_pretty' => 'Fri, Jun 5, 2026',
                        'service_time_pretty' => '9:00 AM - 5:00 PM',
                        'caregiver_first_name' => 'Jon',
                        'client_name' => 'Sansa Stark',
                        'service_name' => 'Childcare',
                        'cancellation_reason' => 'Winter is here.',
                    ],
                ],
            ],
            'template_id' => 'd-2a539fde38bb46788fc96baf7fb6366b',
        ]);

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Booking Request Cancelled',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: ' ', // Setting a space as the content for SendGrid dynamic templates
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
