<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CaregiverTipReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public float $tipAmount,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $serviceDate = $this->booking->start_datetime
            ? $this->booking->start_datetime->copy()->setTimezone('America/Los_Angeles')->format('l, F j, Y')
            : null;

        $clientFirstName = $this->booking->client?->first_name
            ?? $this->booking->bookingGroup?->client_first_name;

        return [
            'title' => 'You received a tip!',
            'message' => 'You received a $'.number_format($this->tipAmount, 2).' tip for booking #'.$this->booking->id.'.',
            'booking_id' => $this->booking->id,
            'tip_amount' => round($this->tipAmount, 2),
            'service_date' => $serviceDate,
            'client_first_name' => $clientFirstName,
        ];
    }
}
