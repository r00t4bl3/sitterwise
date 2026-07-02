<?php

namespace App\Services\Billing;

use App\Enums\BookingStatus;
use App\Events\BookingReceipt;
use App\Models\Booking;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;

class JobBillingService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function charge(Booking $booking): array
    {
        if (! $booking->requires_payment) {
            return [
                'success' => false,
                'message' => 'This booking does not require payment',
            ];
        }

        if ($booking->payment_status === 'charged' || $booking->payment_status === 'succeeded') {
            return [
                'success' => false,
                'message' => 'This booking has already been charged',
            ];
        }

        $client = $booking->client;

        if (! $client->stripe_customer_id) {
            return [
                'success' => false,
                'message' => 'Client does not have a Stripe customer ID',
            ];
        }

        $paymentMethod = ClientPaymentMethod::where('client_id', $client->id)
            ->where('is_default', true)
            ->where('status', 'active')
            ->first();

        if (! $paymentMethod) {
            return [
                'success' => false,
                'message' => 'No default payment method found for client',
            ];
        }

        // We charge the total_service_amount (Service + Reimbursement + Bonus)
        // Tips are charged separately later.
        $totalToCharge = (float) $booking->total_service_amount;

        if ($totalToCharge <= 0) {
            return [
                'success' => false,
                'message' => 'Total service amount must be greater than 0',
            ];
        }

        $amountInCents = (int) round($totalToCharge * 100);

        // Atomically claim this booking so two concurrent confirms (double-click
        // or two admins) can't both reach Stripe and double-charge the client.
        if (! $this->claimForCharge($booking)) {
            return [
                'success' => false,
                'message' => 'This booking is already being charged or has been charged',
            ];
        }

        // 1. Create a trial record in "pending" status
        $clientPayment = ClientPayment::create([
            'booking_id' => $booking->id,
            'client_id' => $client->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => $totalToCharge,
            'currency' => 'usd',
            'status' => 'pending',
            'provider' => 'stripe',
            'metadata' => [
                'base_amount' => (float) $booking->charge_to_client,
                'reimbursement' => (float) ($booking->reimbursement ?? 0),
                'bonus' => (float) ($booking->bonus ?? 0),
                'tip' => (float) ($booking->tip ?? 0),
                'total_service_amount' => $totalToCharge,
                'attempt' => ($booking->charge_attempt_count ?? 0) + 1,
            ],
        ]);

        try {
            // Defense-in-depth against double charges: a stable key per charge
            // attempt lets Stripe collapse duplicate requests (network retries,
            // races) into a single charge. handleFailure() bumps the attempt
            // count, so a legitimate retry after a decline gets a fresh key.
            $idempotencyKey = "booking_{$booking->id}_charge_".($booking->charge_attempt_count ?? 0);

            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $amountInCents,
                'currency' => 'usd',
                'customer' => $client->stripe_customer_id,
                'payment_method' => $paymentMethod->provider_method_id,
                'off_session' => true,
                'confirm' => true,
                'description' => "Booking #{$booking->id} - Sitterwise Service Charge",
                'metadata' => [
                    'booking_id' => $booking->id,
                    'client_id' => $client->id,
                    'total_service_amount' => $totalToCharge,
                ],
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);

            // 2. Update Booking on Success
            $booking->update([
                'status' => BookingStatus::Paid->value,
                'payment_status' => 'charged',
                'stripe_payment_intent_id' => $paymentIntent->id,
                'actual_amount' => $totalToCharge,
                'last_charge_attempt_at' => now(),
            ]);

            // 3. Update ClientPayment on Success
            $clientPayment->update([
                'status' => 'succeeded',
                'provider_payment_id' => $paymentIntent->id,
                'paid_at' => now(),
            ]);

            // 4. Dispatch BookingReceipt event for receipt email with review link
            event(new BookingReceipt($booking));

            return [
                'success' => true,
                'message' => 'Payment successful',
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $totalToCharge,
            ];
        } catch (CardException $e) {
            return $this->handleFailure($booking, $clientPayment, $e);
        } catch (ApiErrorException $e) {
            return $this->handleFailure($booking, $clientPayment, $e);
        }
    }

    /**
     * Atomically claim a booking for charging under a row lock so that two
     * concurrent charge attempts cannot both proceed to Stripe. Returns false
     * when another charge has already succeeded or is currently in flight.
     */
    protected function claimForCharge(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $locked = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return false;
            }

            if (in_array($locked->payment_status, ['charged', 'succeeded'], true)) {
                return false;
            }

            // A "charging" claim from the last 2 minutes is treated as genuinely
            // in flight. An older one is considered abandoned (e.g. the request
            // died mid-charge) and may be retried.
            if (
                $locked->payment_status === 'charging'
                && $locked->last_charge_attempt_at
                && $locked->last_charge_attempt_at->gt(now()->subMinutes(2))
            ) {
                return false;
            }

            $locked->update([
                'payment_status' => 'charging',
                'last_charge_attempt_at' => now(),
            ]);

            // Keep the in-memory instance consistent with the claimed row.
            $booking->setAttribute('payment_status', $locked->payment_status);
            $booking->setAttribute('last_charge_attempt_at', $locked->last_charge_attempt_at);

            return true;
        });
    }

    protected function handleFailure(Booking $booking, ClientPayment $clientPayment, \Exception $e): array
    {
        $booking->increment('charge_attempt_count');
        $booking->update([
            'payment_status' => 'failed',
            'last_charge_attempt_at' => now(),
        ]);

        $errorCode = null;
        $errorMessage = $e->getMessage();

        if ($e instanceof CardException && $e->getError()) {
            $errorCode = $e->getError()->code ?? null;
            $errorMessage = $e->getError()->message ?? $e->getMessage();
        }

        // 4. Update ClientPayment on Failure
        $clientPayment->update([
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);

        app(PaymentFailureHandler::class)->handle($booking, $errorCode, $errorMessage);

        return [
            'success' => false,
            'message' => $errorMessage,
            'error_code' => $errorCode,
        ];
    }

    public function calculateTotal(Booking $booking): float
    {
        return (float) $booking->total_service_amount;
    }
}
