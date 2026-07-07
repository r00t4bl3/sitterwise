<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;

class CaregiverBookingCancelledMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public Booking $booking,
        public string $reason,
    ) {}

    protected function templateId(): string
    {
        return 'd-286f15d2045541babef403f5fde86cef';
    }

    protected function templateData(): array
    {
        $start = $this->booking->start_datetime->copy()->setTimezone('America/Los_Angeles');

        $data = [
            'booking_id' => $this->booking->id,
            'caregiver_first_name' => $this->booking->caregiver?->first_name ?? 'Sitter',
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
        return 'Job #'.$this->booking->id.' Has Been Cancelled';
    }
}
