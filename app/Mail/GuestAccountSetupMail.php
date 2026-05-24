<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class GuestAccountSetupMail extends Mailable
{
    use Queueable, SendGrid, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $resetToken,
    ) {}

    public function envelope(): Envelope
    {
        $email = $this->booking->client?->user?->email ?? $this->booking->client_email;
        $resetUrl = url('/reset-password/'.$this->resetToken.'?email='.urlencode($email));

        $data = $this->booking->toEmailData();
        $data['password_setup_url'] = $resetUrl;

        $this->sendgrid([
            'personalizations' => [
                [
                    'dynamic_template_data' => $data,
                ],
            ],
            'template_id' => 'd-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        ]);

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Set up your Sitterwise account',
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
