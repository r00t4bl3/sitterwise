<?php

namespace App\Mail;

use App\Models\BookingGroup;

class AdminGroupBookingCreatedMail extends SendGridDynamicMail
{
    public function __construct(public BookingGroup $bookingGroup) {}

    protected function templateId(): string
    {
        return 'd-0574fc2a4e9c44eb9ae3038495fb7b6b';
    }

    protected function templateData(): array
    {
        $emailData = $this->bookingGroup->toEmailData();

        $data = [
            'client_first_name' => $this->bookingGroup->client_first_name,
            'client_last_name' => $this->bookingGroup->client_last_name,
            'booking_number' => $this->bookingGroup->bookings->first()?->ulid,
            'service_type' => $emailData['service_name'],
            'location' => $emailData['location'],
            'bookings' => $emailData['dates'],
            'booking_count' => $this->bookingGroup->bookings->count(),
        ];

        if ($emailData['client_email']) {
            $data['client_email'] = $emailData['client_email'];
        }
        if ($emailData['client_phone']) {
            $data['client_phone'] = $emailData['client_phone'];
        }

        return $data;
    }

    protected function subjectLine(): string
    {
        return 'New Group Booking Created';
    }

    protected function bladeView(): ?string
    {
        return 'emails.admin-group-booking-created';
    }

    protected function shouldBccTeam(): bool
    {
        return false;
    }
}
