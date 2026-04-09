<?php

namespace App\Services\Webhooks;

use App\Models\Booking;
use App\Models\ClientPayment;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeWebhookHandler
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function handle(array $payload, string $signature): array
    {
        try {
            $event = Webhook::constructEvent(
                json_encode($payload),
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid webhook payload', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Invalid payload'];
        } catch (SignatureVerificationException $e) {
            Log::error('Invalid webhook signature', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Invalid signature'];
        }

        Log::info('Stripe webhook received', ['type' => $event->type, 'id' => $event->id]);

        return match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentSuccess($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailure($event->data->object),
            'charge.refunded' => $this->handleChargeRefunded($event->data->object),
            default => ['success' => true, 'message' => 'Unhandled event type'],
        };
    }

    protected function handlePaymentSuccess(object $paymentIntent): array
    {
        $bookingId = $paymentIntent->metadata->booking_id ?? null;

        if (! $bookingId) {
            Log::warning('Payment succeeded but no booking_id in metadata', [
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return ['success' => true, 'message' => 'No booking_id found'];
        }

        $booking = Booking::find($bookingId);

        if (! $booking) {
            Log::warning('Booking not found for payment intent', [
                'booking_id' => $bookingId,
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return ['success' => true, 'message' => 'Booking not found'];
        }

        $amount = $paymentIntent->amount / 100;

        $booking->update([
            'payment_status' => 'charged',
            'stripe_payment_intent_id' => $paymentIntent->id,
            'actual_amount' => $amount,
            'last_charge_attempt_at' => now(),
        ]);

        $clientPayment = ClientPayment::where('booking_id', $booking->id)->first();

        if ($clientPayment) {
            $clientPayment->update([
                'status' => 'captured',
                'provider_payment_id' => $paymentIntent->id,
                'paid_at' => now(),
            ]);
        }

        Log::info('Payment succeeded', [
            'booking_id' => $booking->id,
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $amount,
        ]);

        return ['success' => true, 'message' => 'Payment recorded'];
    }

    protected function handlePaymentFailure(object $paymentIntent): array
    {
        $bookingId = $paymentIntent->metadata->booking_id ?? null;

        if (! $bookingId) {
            Log::warning('Payment failed but no booking_id in metadata', [
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return ['success' => true, 'message' => 'No booking_id found'];
        }

        $booking = Booking::find($bookingId);

        if (! $booking) {
            Log::warning('Booking not found for failed payment', [
                'booking_id' => $bookingId,
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return ['success' => true, 'message' => 'Booking not found'];
        }

        $errorMessage = $paymentIntent->last_payment_error?->message ?? 'Payment failed';

        $booking->increment('charge_attempt_count');
        $booking->update([
            'payment_status' => 'failed',
            'last_charge_attempt_at' => now(),
        ]);

        $clientPayment = ClientPayment::where('booking_id', $booking->id)->first();

        if ($clientPayment) {
            $clientPayment->update([
                'status' => 'failed',
                'provider_payment_id' => $paymentIntent->id,
            ]);
        }

        Log::warning('Payment failed', [
            'booking_id' => $booking->id,
            'payment_intent_id' => $paymentIntent->id,
            'error' => $errorMessage,
            'attempt' => $booking->charge_attempt_count,
        ]);

        return ['success' => true, 'message' => 'Payment failure recorded'];
    }

    protected function handleChargeRefunded(object $charge): array
    {
        $paymentIntentId = $charge->payment_intent ?? null;

        if (! $paymentIntentId) {
            return ['success' => true, 'message' => 'No payment_intent found'];
        }

        $booking = Booking::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if (! $booking) {
            return ['success' => true, 'message' => 'Booking not found'];
        }

        $booking->update(['payment_status' => 'refunded']);

        $clientPayment = ClientPayment::where('booking_id', $booking->id)->first();

        if ($clientPayment) {
            $clientPayment->update(['status' => 'refunded']);
        }

        Log::info('Charge refunded', [
            'booking_id' => $booking->id,
            'charge_id' => $charge->id,
        ]);

        return ['success' => true, 'message' => 'Refund recorded'];
    }
}
