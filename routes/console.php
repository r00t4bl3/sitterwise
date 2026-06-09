<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Clean up expired reservations every minute
Schedule::command('bookings:cleanup-expired-reservations')->everyMinute();

// Nudge incomplete applications every 6 hours
Schedule::command('app:nudge-incomplete-applications')->everySixHours();

// Archive stalled applications daily
Schedule::command('app:archive-stalled-applications')->daily();

// Nudge pending references daily at 9am
Schedule::command('app:nudge-pending-references')->dailyAt('09:00');

// Check in on caregivers on hold at 30/45/60 day thresholds
Schedule::command('app:check-in-on-hold-caregivers')->dailyAt('10:00');

// Archive long-term inactive caregivers (warning at 166d, archive at 180d)
Schedule::command('app:archive-long-term-inactive')->daily();

// Check for caregivers with 3+ late arrivals in 60 days
Schedule::command('app:check-late-arrivals')->dailyAt('11:00');

// Recalculate caregiver reliability scores daily
Schedule::command('app:recalculate-reliability')->dailyAt('02:00');

// Send review reminders for completed bookings (every 6 hours)
Schedule::command('app:send-review-reminders')->everySixHours();
