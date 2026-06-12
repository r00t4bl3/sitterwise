<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class ClientPaymentRequiredMail extends Mailable
{
    use Queueable, SendGrid, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        $data = $this->booking->toEmailData();
        $data['payment_link'] = route('bookings.show', $this->booking);

        $this->sendgrid([
            'personalizations' => [
                [
                    'dynamic_template_data' => $data,
                ],
            ],
            'template_id' => 'd-9f4b24bb450140d9bd2c1628b705fbc1',
        ]);

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Payment Required',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: ' ',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
