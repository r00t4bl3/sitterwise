<?php

namespace App\Mail;

use App\Models\BookingGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class ClientGroupBookingCreatedMail extends Mailable
{
    use Queueable, SendGrid, SerializesModels;

    public function __construct(public BookingGroup $bookingGroup) {}

    public function envelope(): Envelope
    {
        $this->sendgrid([
            'personalizations' => [
                [
                    'dynamic_template_data' => $this->bookingGroup->toEmailData(),
                ],
            ],
            'template_id' => 'd-c691618c009e4289a937774e33975817', // Same template as single booking
        ]);

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Group Booking Request Received',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: ' ',
        );
    }
}
