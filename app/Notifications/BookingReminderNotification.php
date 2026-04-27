<?php

namespace App\Notifications;

use App\Mail\CaregiverBookingReminderMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class BookingReminderNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    protected function channels(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable)
    {
        return new CaregiverBookingReminderMail($this->booking);
    }

    public function toArray(object $notifiable): array
    {
        $clientName = ($this->booking->client?->first_name ?? $this->booking->client_first_name);
        $time = $this->booking->start_datetime->format('g:i A');

        return [
            'booking_id' => $this->booking->id,
            'title' => 'Upcoming Job Reminder',
            'message' => "Reminder: You have a scheduled job with {$clientName} tomorrow at {$time}.",
            'type' => 'booking_reminder',
        ];
    }
}
