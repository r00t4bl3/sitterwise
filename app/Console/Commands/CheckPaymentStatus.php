<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Stripe\StripeClient;

#[Signature('payments:check-status {bookings* : One or more booking IDs to check}')]
#[Description('Look up the Stripe PaymentIntent status for one or more bookings')]
class CheckPaymentStatus extends Command
{
    public function handle(): int
    {
        $stripe = new StripeClient(config('services.stripe.secret'));
        $ids = $this->argument('bookings');

        foreach ($ids as $id) {
            $booking = Booking::with('client')->find($id);

            if (! $booking) {
                $this->error("Booking #{$id}: not found");

                continue;
            }

            $piId = $booking->stripe_payment_intent_id;

            if (! $piId) {
                $this->warn("Booking #{$id}: no stripe_payment_intent_id on record");
                $this->line('  Local status: '.$booking->status.' / payment: '.$booking->payment_status);

                continue;
            }

            try {
                $pi = $stripe->paymentIntents->retrieve($piId);
            } catch (\Exception $e) {
                $this->error("Booking #{$id}: Stripe API error: {$e->getMessage()}");

                continue;
            }

            $charge = $pi->charges->data[0] ?? null;
            $amount = ($pi->amount_received ?: $pi->amount) / 100;
            $receiptUrl = $charge->receipt_url ?? null;

            $this->line("--- Booking #{$id} ---");
            $this->line('  Local status:         '.$booking->status);
            $this->line('  Local payment_status: '.$booking->payment_status);
            $this->line('  Stripe PI:            '.$pi->id);
            $this->line('  Stripe status:        '.$pi->status);
            $this->line('  Amount:               $'.$amount);
            $this->line('  Currency:             '.strtoupper($pi->currency));
            $this->line('  Customer:             '.$pi->customer);

            if ($receiptUrl) {
                $this->line('  Receipt:              '.$receiptUrl);
            }

            $this->line('');
        }

        return 0;
    }
}
