<?php

namespace App\Console\Commands;

use App\Events\BookingReminderTriggered;
use App\Models\Booking;
use App\Support\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-booking-reminders')]
#[Description('Send booking reminders for confirmed bookings starting in ~24 hours')]
class SendBookingReminders extends Command
{
    public function handle(): void
    {
        $sent = 0;

        // Reminder is sent in the final 1-hour window ending at the lead time.
        $leadHours = (int) Settings::get('bookings.reminder_hours_before', 24);

        $bookings = Booking::where('status', 'confirmed')
            ->whereBetween('start_datetime', [now()->addHours($leadHours - 1), now()->addHours($leadHours)])
            ->get();

        foreach ($bookings as $booking) {
            BookingReminderTriggered::dispatch($booking);
            $sent++;
        }

        $this->line("Booking reminders sent: {$sent}");
    }
}
