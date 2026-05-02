<?php

namespace App\Listeners;

use App\Events\BookingReceipt;
use App\Notifications\BookingReceiptNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingReceiptNotification implements ShouldQueue
{
    public function handle(BookingReceipt $event): void
    {
        if ($event->booking->client && $event->booking->client->user) {
            $event->booking->client->user->notify(
                new BookingReceiptNotification($event->booking)
            );
        }
    }
}
