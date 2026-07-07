<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;

class ClientBookingCancelledMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public Booking $booking,
        public string $reason,
    ) {}

    protected function templateId(): string
    {
        return 'd-965c67b476c54002b0912d87f5805303';
    }

    protected function templateData(): array
    {
        $start = $this->booking->start_datetime->copy()->setTimezone('America/Los_Angeles');
        $firstName = $this->booking->client?->first_name ?? $this->booking->bookingGroup?->client_first_name;

        $data = [
            'booking_id' => $this->booking->id,
            'client_first_name' => $firstName ?: 'Valued Client',
            'service_type' => $this->booking->service_type_label,
            'start_date' => $start->format('l, F j, Y'),
            'start_time' => $start->format('g:i A'),
        ];

        if ($this->reason !== '') {
            $data['reason'] = $this->reason;
        }

        return $data;
    }

    protected function subjectLine(): string
    {
        return 'Your Booking Has Been Cancelled';
    }
}
