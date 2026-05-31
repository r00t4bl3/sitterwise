<?php

namespace App\Mail;

use App\Models\BookingGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class AdminGroupBookingCreatedMail extends Mailable
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
            'template_id' => 'd-de8ddf0050cf4ec29caee8c210c6263f', // Same template as single booking
        ]);

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'New Group Booking Created',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: ' ',
        );
    }
}
