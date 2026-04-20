<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class JobReminder extends Mailable
{
    use Queueable, SendGrid, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $this->sendgrid([
            'personalizations' => [
                [
                    'dynamic_template_data' => [
                        'client_name' => 'Jason Smith',
                        'service_time' => '9:00 AM',
                        'service_date_pretty' => 'Wednesday, June 12th, 2024',
                        'bio_link' => 'https://sitterwise.io/caregivers/12345',
                    ],
                ],
            ],
            'template_id' => 'd-c141f95e479746dd8af8d96aa1c64067',
        ]);

        return new Envelope(
            from: 'admin@sitterwise.io',
            // replyTo: 'reply@example.com',
            // subject: 'Caregiver Job Invitation',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: ' ', // Setting a space as the content
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
