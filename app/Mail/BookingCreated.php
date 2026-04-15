<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class BookingCreated extends Mailable
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
                        'client_name'            => 'Jason Smith',
                        'date'                   => 'Wednesday, September 27, 2023',
                        'time'                   => '9:00 AM',
                        'client_email'           => 'jason.smith@example.com',
                        'client_phone'           => '(555) 123-4567',
                        'service_name'           => 'Caregiving',
                        'address'                => '123 Main St, Anytown, USA',
                        'hotel_name'             => 'Grand Hotel',
                        'is_hotel_text'          => 'Grand Hotel Booking',
                        'kids_count'             => '2 children',
                        'children_summary'       => '2 children, ages 3 and 5',
                        'special_considerations' => 'Sitter must be comfortable with pets',
                        'notes_to_sitter'        => 'Please bring snacks for the kids.',
                        'notes_to_admin'         => 'Client prefers a sitter with experience in early childhood education.',
                        'admin_booking_url'      => 'https://admin.sitterwise.io/bookings/12345',
                        'end_time'               => '11:00 AM',
                    ],
                ],
            ],
            'template_id'      => 'd-de8ddf0050cf4ec29caee8c210c6263f',
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