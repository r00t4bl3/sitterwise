<?php

namespace App\Mail;

use App\Models\BookingGroup;
use Illuminate\Support\Facades\Password;

class ClientGroupBookingCreatedMail extends SendGridDynamicMail
{
    public function __construct(public BookingGroup $bookingGroup) {}

    protected function templateId(): string
    {
        return 'd-9304fcd2ccf046e6913979cdfbb7a6c5';
    }

    protected function templateData(): array
    {
        $emailData = $this->bookingGroup->toEmailData();

        $data = [
            'client_first_name' => $this->bookingGroup->client_first_name,
            'booking_number' => $this->bookingGroup->bookings->first()?->ulid,
            'service_type' => $emailData['service_name'],
            'location' => $emailData['location'],
            'bookings' => $emailData['dates'],
        ];

        // Guest submissions carry a password-setup link so the client can claim
        // their new account from the email.
        $user = $this->bookingGroup->client?->user;
        if ($user && $this->bookingGroup->submission_type === 'guest') {
            $token = Password::broker()->createToken($user);
            $data['password_setup_url'] = url('/reset-password/'.$token.'?email='.urlencode($user->email));
        }

        return $data;
    }

    protected function subjectLine(): string
    {
        return 'Group Booking Request Received';
    }
}
