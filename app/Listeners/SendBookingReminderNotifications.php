<?php

namespace App\Listeners;

use App\Enums\BookingStatus;
use App\Events\BookingReminderTriggered;
use App\Notifications\BookingReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingReminderNotifications implements ShouldQueue
{
    public function handle(BookingReminderTriggered $event): void
    {
        // Re-check current state: the booking may have been cancelled (or the
        // assignment resolved) between the hourly dispatch and this queued job,
        // in which case the caregiver must not receive a reminder.
        $booking = $event->booking->fresh();

        if (! $booking || $booking->status !== BookingStatus::Confirmed->value) {
            return;
        }

        // Bookings that track assignments must still have an active one. Legacy
        // bookings without any assignment rows are gated by status alone.
        if ($booking->assignments()->exists() && ! $booking->assignments()->unresolved()->exists()) {
            return;
        }

        if ($booking->caregiver && $booking->caregiver->user) {
            $booking->caregiver->user->notify(new BookingReminderNotification($booking));
        }
    }
}
