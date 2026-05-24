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
