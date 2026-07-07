<?php

namespace App\Console\Commands;

use App\Events\BookingReminderTriggered;
use App\Models\Booking;
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

        $bookings = Booking::where('status', 'confirmed')
            ->whereBetween('start_datetime', [now()->addHours(23), now()->addHours(24)])
            ->get();

        foreach ($bookings as $booking) {
            BookingReminderTriggered::dispatch($booking);
            $sent++;
        }

        $this->line("Booking reminders sent: {$sent}");
    }
}
