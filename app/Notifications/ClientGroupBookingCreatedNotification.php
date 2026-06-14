<?php

namespace App\Notifications;

use App\Mail\ClientGroupBookingCreatedMail;
use App\Models\BookingGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ClientGroupBookingCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public BookingGroup $bookingGroup) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable)
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        return (new ClientGroupBookingCreatedMail($this->bookingGroup))->to($address);
    }

    public function toArray(object $notifiable): array
    {
        $firstBooking = $this->bookingGroup->bookings->first();

        return [
            'booking_group_id' => $this->bookingGroup->id,
            'booking_id' => $firstBooking?->id,
            'title' => 'Group Booking Received',
            'message' => "We've received your multi-date booking request for ".$this->bookingGroup->bookings->count().' dates.',
            'type' => 'booking_created',
            'dates_count' => $this->bookingGroup->bookings->count(),
        ];
    }
}
