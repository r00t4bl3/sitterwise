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
    // Dual-mode proof-of-concept for the SendGrid migration (#21). Setting BOTH a
    // Blade content() body AND a sendgrid() dynamic template lets the SAME mailable
    // render locally/in tests and send the branded template in production:
    //   - The SendGrid trait's sendgrid() is a NO-OP unless config('mail.default')
    //     === 'sendgrid', so on array/log/smtp (local + tests) the Blade body below
    //     renders and can be previewed/asserted.
    //   - In production (mail.default = sendgrid) the template is embedded and
    //     SendGrid renders it, ignoring the message body.
    use Queueable, SendGrid, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        $this->sendgrid([
            'personalizations' => [
                ['dynamic_template_data' => $this->booking->toEmailData()],
            ],
            'template_id' => 'd-2a539fde38bb46788fc96baf7fb6366b',
        ]);

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Booking Request Cancelled',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sendgrid-poc',
            with: [
                'marker' => 'BLADE_BODY_RENDERED',
                'bookingId' => $this->booking->id,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
