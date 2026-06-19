<?php

namespace App\Mail;

use App\Models\BookingGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Password;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class ClientGroupBookingCreatedMail extends Mailable
{
    use Queueable, SendGrid, SerializesModels;

    public function __construct(public BookingGroup $bookingGroup) {}

    public function envelope(): Envelope
    {
        $data = $this->bookingGroup->toEmailData();

        $user = $this->bookingGroup->client?->user;
        if ($user && $this->bookingGroup->submission_type === 'guest') {
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
