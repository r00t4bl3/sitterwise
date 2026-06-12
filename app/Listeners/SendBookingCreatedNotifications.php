<?php

namespace App\Listeners;

use App\Enums\BookingPaymentStatus;
use App\Events\BookingCreated;
use App\Models\User;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\ClientPaymentRequiredNotification;
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

        // 3. Notify client about payment required
        if (
            $event->booking->requires_payment
            && $event->booking->payment_status === BookingPaymentStatus::Pending->value
            && $event->booking->client
            && $event->booking->client->user
        ) {
            $event->booking->client->user->notify(new ClientPaymentRequiredNotification($event->booking));
        }
    }
}
