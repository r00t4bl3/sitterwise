<?php

namespace App\Mail;

use App\Models\Booking;

class CaregiverBookingInvitationMail extends SendGridDynamicMail
{
    public function __construct(public Booking $booking) {}

    protected function shouldBccTeam(): bool
    {
        return false;
    }

    protected function templateId(): string
    {
        return 'd-aac404a830334ae884098a75cb32caca';
    }

    protected function templateData(): array
    {
        $group = $this->booking->bookingGroup;
        $start = $this->booking->start_datetime->copy()->setTimezone('America/Los_Angeles');
        $end = $this->booking->end_datetime->copy()->setTimezone('America/Los_Angeles');

        $data = [
            'client_first_name' => $this->booking->client?->first_name ?? $group?->client_first_name,
            'client_last_name' => $this->booking->client?->last_name ?? $group?->client_last_name,
            'date' => $start->format('l, F j, Y'),
            'start_datetime' => $start->format('M j, Y g:i A'),
            'end_datetime' => $end->format('M j, Y g:i A'),
            'job_url' => route('jobs.short', $this->booking),
        ];

        $clientPhone = $this->booking->client?->phone ?? $group?->client_phone;
        if ($clientPhone) {
            $data['client_phone'] = $clientPhone;
        }
        if ($group?->address_line1) {
            $data['address_line1'] = $group->address_line1;
        }
        if ($group?->address_city) {
            $data['address_city'] = $group->address_city;
        }

        return $data;
    }

    protected function subjectLine(): string
    {
        return 'New Booking Available - '.($this->booking->client?->first_name ?? $this->booking->client_first_name);
    }

    protected function bladeView(): ?string
    {
        return 'emails.booking-notification';
    }
}
