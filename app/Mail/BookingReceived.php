<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class BookingReceived extends Mailable
{
    use Queueable, SerializesModels, SendGrid;

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
                        'client_first_name'      => 'Jason',
                        'service_requested'      => 'Caregiving',
                        'date'                   => 'Wednesday, September 27, 2023',
                        'children_summary'       => '2 children, ages 3 and 5',
                        'location'               => '123 Main St, Anytown, USA',
                        'is_hotel'               => 'Grand Hotel',
                        'special_considerations' => 'Sitter must be comfortable with pets',
                        'notes_for_sitter'       => 'Please bring snacks for the kids.',
                        'hourly_rate'            => '15.00',
                        'client_name'            => 'Jason Smith',
                        'start_time'             => '9:00 AM',
                        'end_time'               => '4:00 PM',
                    ],
                ],
            ],
            'template_id'      => 'd-c691618c009e4289a937774e33975817',
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
