<?php

namespace App\Listeners;

use App\Events\GuestAccountSetup;
use App\Notifications\GuestAccountSetupNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendGuestAccountSetupNotification implements ShouldQueue
{
    public function handle(GuestAccountSetup $event): void
    {
        if ($event->booking->client && $event->booking->client->user) {
            $event->booking->client->user->notify(
                new GuestAccountSetupNotification($event->booking, $event->resetToken),
            );
        }
    }
}
