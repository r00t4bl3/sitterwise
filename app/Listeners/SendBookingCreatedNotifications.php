<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Models\User;
use App\Notifications\BookingCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendBookingCreatedNotifications implements ShouldQueue
{
    public function handle(BookingCreated $event): void
    {
        // 1. Notify the Client
        if ($event->booking->client && $event->booking->client->user) {
            $event->booking->client->user->notify(new BookingCreatedNotification($event->booking));
        }

        // 2. Notify all Admins
        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new BookingCreatedNotification($event->booking));
    }
}
