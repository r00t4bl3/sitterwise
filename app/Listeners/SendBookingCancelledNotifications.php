<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendBookingCancelledNotifications implements ShouldQueue
{
    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking->load('client.user', 'caregiver.user');

        // 1. Notify the Client
        if ($booking->client && $booking->client->user) {
            $booking->client->user->notify(new BookingCancelledNotification(
                booking: $booking,
                reason: $event->reason,
                cancelledBy: $event->cancelledBy,
            ));
        }

        // 2. Notify the Caregiver (if assigned)
        if ($booking->caregiver && $booking->caregiver->user) {
            $booking->caregiver->user->notify(new BookingCancelledNotification(
                booking: $booking,
                reason: $event->reason,
                cancelledBy: $event->cancelledBy,
            ));
        }

        // 3. Notify all Admins
        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new BookingCancelledNotification(
            booking: $booking,
            reason: $event->reason,
            cancelledBy: $event->cancelledBy,
        ));
    }
}
