<?php

namespace App\Notifications;

use App\Mail\ClientReceiptMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class BookingReceiptNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    protected function channels(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable)
    {
        return new ClientReceiptMail($this->booking);
    }

    public function toArray(object $notifiable): array
    {
        $clientName = ($this->booking->client?->first_name ?? $this->booking->client_first_name).' '.($this->booking->client?->last_name ?? $this->booking->client_last_name);

        return [
            'booking_id' => $this->booking->id,
            'title' => 'Booking Receipt',
            'message' => "Your receipt for booking #{$this->booking->id} is ready. Click to review your caregiver.",
            'type' => 'booking_receipt',
        ];
    }
}
