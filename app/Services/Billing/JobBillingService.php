<?php

namespace App\Services\Billing;

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

        $baseAmount = (float) $booking->total_amount;
        $reimbursement = (float) ($booking->reimbursement ?? 0);
        $tip = (float) ($booking->tip ?? 0);
        $totalAmount = $baseAmount + $reimbursement + $tip;

        $amountInCents = (int) round($totalAmount * 100);

        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $amountInCents,
                'currency' => 'usd',
                'customer' => $client->stripe_customer_id,
                'payment_method' => $paymentMethod->provider_method_id,
                'off_session' => true,
                'confirm' => true,
                'description' => "Booking #{$booking->id} - Sitterwise",
                'metadata' => [
                    'booking_id' => $booking->id,
                    'client_id' => $client->id,
                    'base_amount' => $baseAmount,
                    'reimbursement' => $reimbursement,
                    'tip' => $tip,
                ],
            ]);

            $booking->update([
                'payment_status' => 'charged',
                'stripe_payment_intent_id' => $paymentIntent->id,
                'actual_amount' => $totalAmount,
                'last_charge_attempt_at' => now(),
            ]);

            ClientPayment::create([
                'booking_id' => $booking->id,
                'client_id' => $client->id,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $totalAmount,
                'currency' => 'usd',
                'status' => 'captured',
                'provider' => 'stripe',
                'provider_payment_id' => $paymentIntent->id,
                'paid_at' => now(),
                'metadata' => [
                    'base_amount' => $baseAmount,
                    'reimbursement' => $reimbursement,
                    'tip' => $tip,
                    'total_charged' => $totalAmount,
                ],
            ]);

            return [
                'success' => true,
                'message' => 'Payment successful',
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $totalAmount,
            ];
        } catch (CardException $e) {
            return $this->handleFailure($booking, $e);
        } catch (ApiErrorException $e) {
            return $this->handleFailure($booking, $e);
        }
    }

    protected function handleFailure(Booking $booking, \Exception $e): array
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

        app(PaymentFailureHandler::class)->handle($booking, $errorCode, $errorMessage);

        return [
            'success' => false,
            'message' => $errorMessage,
            'error_code' => $errorCode,
        ];
    }

    public function calculateTotal(Booking $booking): float
    {
        $baseAmount = (float) $booking->total_amount;
        $reimbursement = (float) ($booking->reimbursement ?? 0);
        $tip = (float) ($booking->tip ?? 0);

        return $baseAmount + $reimbursement + $tip;
    }
}
