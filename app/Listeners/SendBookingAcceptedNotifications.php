<?php

namespace App\Listeners;

use App\Events\BookingAccepted;
use App\Models\User;
use App\Notifications\BookingAcceptedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendBookingAcceptedNotifications implements ShouldQueue
{
    public function handle(BookingAccepted $event): void
    {
        // 1. Notify the Client
        if ($event->booking->client && $event->booking->client->user) {
            $event->booking->client->user->notify(new BookingAcceptedNotification($event->booking));
        }

        // 2. Notify the Caregiver
        if ($event->booking->caregiver && $event->booking->caregiver->user) {
            $event->booking->caregiver->user->notify(new BookingAcceptedNotification($event->booking));
        }

        // 3. Notify Admins
        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new BookingAcceptedNotification($event->booking));
    }
}
