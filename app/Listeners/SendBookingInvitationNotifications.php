<?php

namespace App\Listeners;

use App\Events\BookingInvitationSent;
use App\Notifications\BookingInvitationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingInvitationNotifications implements ShouldQueue
{
    public function handle(BookingInvitationSent $event): void
    {
        if ($event->caregiver->user) {
            $event->caregiver->user->notify(new BookingInvitationNotification($event->booking));
        }
    }
}
