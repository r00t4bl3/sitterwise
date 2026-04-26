<?php

namespace App\Listeners;

use App\Events\BookingReminderTriggered;
use App\Notifications\BookingReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingReminderNotifications implements ShouldQueue
{
    public function handle(BookingReminderTriggered $event): void
    {
        if ($event->booking->caregiver && $event->booking->caregiver->user) {
            $event->booking->caregiver->user->notify(new BookingReminderNotification($event->booking));
        }
    }
}
