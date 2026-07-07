<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class AdminBookingCancelledMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public Booking $booking,
        public string $reason,
        public User $cancelledBy,
    ) {}

    protected function templateId(): string
    {
        return 'd-71b39db4e170449fba2de7234e8d5961';
    }

    protected function templateData(): array
    {
        $group = $this->booking->bookingGroup;
        $start = $this->booking->start_datetime->copy()->setTimezone('America/Los_Angeles');

        $clientName = trim(
            ($this->booking->client?->first_name ?? $group?->client_first_name).' '.
            ($this->booking->client?->last_name ?? $group?->client_last_name)
        );

        $data = [
            'booking_id' => $this->booking->id,
            'cancelled_by' => $this->cancelledBy->name,
            'client_name' => $clientName ?: 'N/A',
            'service_type' => $this->booking->service_type_label,
            'start_date' => $start->format('l, F j, Y'),
            'start_time' => $start->format('g:i A'),
            'booking_url' => url('/bookings/'.$this->booking->id),
        ];

        if ($this->booking->caregiver) {
            $data['caregiver_name'] = $this->booking->caregiver->first_name.' '.$this->booking->caregiver->last_name;
        }

        if ($this->reason !== '') {
            $data['reason'] = $this->reason;
        }

        return $data;
    }

    protected function subjectLine(): string
    {
        return 'Booking #'.$this->booking->id.' Has Been Cancelled';
    }

    protected function shouldBccTeam(): bool
    {
        return false;
    }
}
