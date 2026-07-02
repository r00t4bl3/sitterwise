<?php

namespace App\Console\Commands;

use App\Channels\SmsChannel;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Notifications\BookingReviewReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-review-reminders')]
#[Description('Send review reminders for completed bookings without a caregiver rating')]
class SendReviewReminders extends Command
{
    public function handle()
    {
        $completedStatuses = ['completed', 'paid'];

        $emailSent = 0;
        $smsSent = 0;

        // Email window: 2–26 hours after booking completion. The command runs
        // every few hours, so the "already sent" timestamp stops a booking from
        // being reminded again on every run within the window.
        $emailCandidates = Booking::whereIn('status', $completedStatuses)
            ->whereNull('review_reminder_email_sent_at')
            ->where('end_datetime', '>=', now()->subHours(26))
            ->where('end_datetime', '<', now()->subHours(2))
            ->whereDoesntHave('ratings', function ($q) {
                $q->where('ratable_type', Caregiver::class);
            })
            ->get();

        foreach ($emailCandidates as $booking) {
            $user = $booking->client?->user;

            if (! $user) {
                continue;
            }

            $user->notify(new BookingReviewReminderNotification($booking, ['mail']));
            $booking->forceFill(['review_reminder_email_sent_at' => now()])->saveQuietly();
            $emailSent++;
        }

        // SMS window: 48–72 hours after booking completion.
        $smsCandidates = Booking::whereIn('status', $completedStatuses)
            ->whereNull('review_reminder_sms_sent_at')
            ->where('end_datetime', '>=', now()->subHours(72))
            ->where('end_datetime', '<', now()->subHours(48))
            ->whereDoesntHave('ratings', function ($q) {
                $q->where('ratable_type', Caregiver::class);
            })
            ->get();

        foreach ($smsCandidates as $booking) {
            $user = $booking->client?->user;

            if (! $user) {
                continue;
            }

            $user->notify(new BookingReviewReminderNotification($booking, [SmsChannel::class]));
            $booking->forceFill(['review_reminder_sms_sent_at' => now()])->saveQuietly();
            $smsSent++;
        }

        $this->line("Review reminder emails sent: {$emailSent}");
        $this->line("Review reminder SMS sent: {$smsSent}");
    }
}
