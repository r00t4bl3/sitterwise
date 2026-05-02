<?php

namespace App\Services\Billing;

use App\Models\Booking;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;

class TipChargeService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function charge(Booking $booking, float $tipAmount): array
    {
        $existingPending = ClientPayment::where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->whereJsonContains('metadata->type', 'tip')
            ->first();

        if ($existingPending) {
            return [
                'success' => false,
                'message' => 'A tip payment is already pending for this booking',
            ];
        }

        if ($tipAmount <= 0) {
            return ['success' => false, 'message' => 'Tip amount must be greater than 0'];
        }

        $client = $booking->client;

        if (! $client->stripe_customer_id) {
            return ['success' => false, 'message' => 'Client does not have a Stripe customer ID'];
        }

        $paymentMethod = ClientPaymentMethod::where('client_id', $client->id)
            ->where('is_default', true)
            ->where('status', 'active')
            ->first();

        if (! $paymentMethod) {
            return ['success' => false, 'message' => 'No default payment method found'];
        }

        $amountInCents = (int) round($tipAmount * 100);

        $clientPayment = ClientPayment::create([
            'booking_id' => $booking->id,
            'client_id' => $client->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => $tipAmount,
            'currency' => 'usd',
            'status' => 'pending',
            'provider' => 'stripe',
            'metadata' => [
                'type' => 'tip',
                'booking_id' => $booking->id,
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
                'description' => "Booking #{$booking->id} - Tip for Caregiver",
                'metadata' => [
                    'booking_id' => $booking->id,
                    'type' => 'tip',
                ],
            ]);

            $booking->update(['tip' => $tipAmount]);

            $clientPayment->update([
                'status' => 'captured',
                'provider_payment_id' => $paymentIntent->id,
                'paid_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'Tip charged successfully',
                'payment_intent_id' => $paymentIntent->id,
            ];
        } catch (CardException $e) {
            return $this->handleFailure($booking, $clientPayment, $e);
        } catch (ApiErrorException $e) {
            return $this->handleFailure($booking, $clientPayment, $e);
        }
    }

    protected function handleFailure(Booking $booking, ClientPayment $clientPayment, \Exception $e): array
    {
        $errorCode = null;
        $errorMessage = $e->getMessage();

        if ($e instanceof CardException && $e->getError()) {
            $errorCode = $e->getError()->code ?? null;
            $errorMessage = $e->getError()->message ?? $e->getMessage();
        }

        $clientPayment->update([
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);

        return [
            'success' => false,
            'message' => $errorMessage,
            'error_code' => $errorCode,
        ];
    }
}
