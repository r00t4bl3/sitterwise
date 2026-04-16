<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class CareComJobAccepted extends Mailable
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
                        'client_name' => 'John Doe',
                        'date' => '2023-10-10',
                        'caregiver_first_name' => 'Jane',
                        'service_name' => 'Elderly Care',
                        'date_times' => 'Wednesday, October 10, 2023',
                        'client_phone' => '123-456-7890',
                        'address' => '123 Main St, Anytown, USA',
                        'is_hotel' => false,
                        'children_summary' => '2 children, ages 5 and 8',
                        'special_considerations' => 'Allergic to peanuts, needs help with mobility',
                        'notes' => 'Client prefersacaregiverwithexperienceinelderlycare and is lookingfor someone whocanprovidecompanionship as well as assistance withdailyactivities . ',
                        'service_times' => 'Monday toFriday, 9:00AM - 5:00PM',
                        'job_id' => 12345,
                        'bio_link' => 'https://sitterwise.io/caregivers/jane-doe',
                    ],
                ],
            ],
            'template_id' => 'd-3f3ed05c7e5f4c40bcdffbc967ef8bdb',
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
