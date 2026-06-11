<?php

namespace App\Notifications;

use App\Mail\AdminGroupBookingCreatedMail;
use App\Models\BookingGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdminGroupBookingCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public BookingGroup $bookingGroup) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        return (new AdminGroupBookingCreatedMail($this->bookingGroup))->to($address);
    }
}
