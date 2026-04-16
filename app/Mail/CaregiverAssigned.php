<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class CaregiverAssigned extends Mailable
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
                        'client_first_name' => 'Jason',
                        'sitter_name' => 'Jane Doe',
                        'sitter_phone' => '123-456-7890',
                        'sitter_profile_url' => 'https://sitterwise.io/caregivers/jane-doe',
                        'service_name' => 'Elderly Care',
                        'start_time' => '9:00 AM',
                        'children_summary' => '2 children, ages 5 and 8',
                        'location' => '123 Main St, Anytown, USA',
                        'hotel_name' => 'Grand Hotel',
                        'date' => 'Monday, September 25, 2023',
                        'sitter_first_name' => 'Jane',
                        'client_name' => 'Jason Smith',
                        'end_time' => '5:00 PM',
                    ],
                ],
            ],
            'template_id' => 'd-3cdfa4a1b83746009e07db0f0261afa4',
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
