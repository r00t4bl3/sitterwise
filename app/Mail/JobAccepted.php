<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class JobAccepted extends Mailable
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
                        'caregiver_name' => 'Jane Doe',
                        'service_date_pretty' => 'Wed, Sep 27, 2023',
                        'service_time_range' => '9:00 AM - 5:00 PM',
                        'service_location' => '123 Main St, Anytown, USA',
                        'service_hotel' => 'Grand Hotel',
                        'cg_url' => 'https://sitterwise.com/caregivers/123',
                        'job_id' => '456',
                    ],
                ],
            ],
            'template_id' => 'd-636bec70c9e74cf8a708086896e84539',
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
