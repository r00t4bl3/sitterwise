<?php

namespace App\Services\Billing;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
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

        if ($booking->payment_status === 'charged' || $booking->payment_status === 'captured') {
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
                'status' => 'captured',
                'provider_payment_id' => $paymentIntent->id,
                'paid_at' => now(),
            ]);

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
