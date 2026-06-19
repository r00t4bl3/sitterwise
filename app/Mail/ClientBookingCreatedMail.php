<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Password;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class ClientBookingCreatedMail extends Mailable
{
    use Queueable, SendGrid, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        $data = $this->booking->toEmailData();

        $user = $this->booking->client?->user;
        if ($user && $this->booking->bookingGroup?->submission_type === 'guest') {
            $token = Password::broker()->createToken($user);
            $data['password_setup_url'] = url('/reset-password/'.$token.'?email='.urlencode($user->email));
        }

        $this->sendgrid([
            'personalizations' => [
                [
                    'dynamic_template_data' => $data,
                ],
            ],
            'template_id' => 'd-53f1d52866924c3096bd0d7deae965e6',
        ]);

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Booking Request Received',
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
