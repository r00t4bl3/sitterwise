<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\PricingRule;
use App\Notifications\ClientPaymentSmsReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-payment-sms-reminders')]
#[Description('Send SMS reminders for unpaid bookings that are 24+ hours old')]
class SendPaymentSmsReminders extends Command
{
    public function handle(): void
    {
        $sent = 0;

        $bookings = Booking::whereHas('bookingGroup', fn ($q) => $q
            ->where('requires_payment', true)
            ->where('payment_form', PricingRule::PAYMENT_FORM_STRIPE))
            ->where('payment_status', 'pending')
            // A cancelled booking needs no payment, so it must never trigger a
            // "please add your payment info" reminder.
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->whereNull('payment_reminder_sent_at')
            ->where('created_at', '<', now()->subHours(24))
            ->get();

        foreach ($bookings as $booking) {
            $client = $booking->client;
            $user = $client?->user;

            if (! $user) {
                continue;
            }

            // The reminder asks the client to add their payment info. A booking
            // stays payment_status = 'pending' until care is complete (we charge
            // afterwards), so a client who already has a card on file would still
            // match the query — skip them, or they get an "add your card" text
            // when their card is already saved.
            if ($client->hasPaymentMethod()) {
                continue;
            }

            $user->notify(new ClientPaymentSmsReminderNotification($booking));
            $booking->update(['payment_reminder_sent_at' => now()]);
            $sent++;
        }

        $this->line("Payment SMS reminders sent: {$sent}");
    }
}
