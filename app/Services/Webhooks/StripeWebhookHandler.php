<?php

namespace App\Services\Webhooks;

use App\Models\Booking;
use App\Models\ClientPayment;
use App\Services\Billing\PaymentFailureHandler;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookHandler
{
    public function handle(array $payload, string $signature): array
    {
        try {
            $event = Webhook::constructEvent(
                json_encode($payload),
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook: invalid signature');

            return [
                'success' => false,
                'message' => 'Invalid signature',
            ];
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook: invalid payload');

            return [
                'success' => false,
                'message' => 'Invalid payload',
            ];
        }

        Log::info('Stripe webhook: event received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event->data->object);
                break;

            default:
                Log::warning('Stripe webhook: unhandled event type', [
                    'type' => $event->type,
                    'id' => $event->id,
                ]);
        }

        return [
            'success' => true,
        ];
    }

    protected function handlePaymentIntentSucceeded($paymentIntent): void
    {
        $bookingId = $paymentIntent->metadata->booking_id ?? null;

        if (! $bookingId) {
            Log::warning('Stripe webhook: payment_intent.succeeded missing booking_id in metadata', [
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return;
        }

        $booking = Booking::find($bookingId);

        if (! $booking) {
            Log::warning('Stripe webhook: payment_intent.succeeded booking not found', [
                'payment_intent_id' => $paymentIntent->id,
                'booking_id' => $bookingId,
            ]);

            return;
        }

        Log::info('Stripe webhook: payment_intent.succeeded processing', [
            'payment_intent_id' => $paymentIntent->id,
            'booking_id' => $booking->id,
        ]);

        $booking->update([
            'payment_status' => 'charged',
            'stripe_payment_intent_id' => $paymentIntent->id,
            'actual_amount' => $paymentIntent->amount / 100,
            'last_charge_attempt_at' => now(),
        ]);

        $clientPayment = ClientPayment::where('booking_id', $bookingId)
            ->where('provider_payment_id', $paymentIntent->id)
            ->orWhere(function ($query) use ($bookingId) {
                $query->where('booking_id', $bookingId)
                    ->where('status', 'pending');
            })
            ->first();

        if ($clientPayment) {
            $clientPayment->update([
                'status' => 'captured',
                'provider_payment_id' => $paymentIntent->id,
                'paid_at' => now(),
            ]);

            Log::info('Stripe webhook: client payment captured', [
                'payment_intent_id' => $paymentIntent->id,
                'client_payment_id' => $clientPayment->id,
            ]);
        } else {
            Log::info('Stripe webhook: no pending client payment found to capture', [
                'payment_intent_id' => $paymentIntent->id,
                'booking_id' => $booking->id,
            ]);
        }
    }

    protected function handlePaymentIntentFailed($paymentIntent): void
    {
        $bookingId = $paymentIntent->metadata->booking_id ?? null;

        if (! $bookingId) {
            Log::warning('Stripe webhook: payment_intent.payment_failed missing booking_id in metadata', [
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return;
        }

        $booking = Booking::find($bookingId);

        if (! $booking) {
            Log::warning('Stripe webhook: payment_intent.payment_failed booking not found', [
                'payment_intent_id' => $paymentIntent->id,
                'booking_id' => $bookingId,
            ]);

            return;
        }

        $errorCode = $paymentIntent->last_payment_error?->code ?? 'unknown';
        $errorMessage = $paymentIntent->last_payment_error?->message ?? 'Payment failed';

        Log::info('Stripe webhook: payment_intent.payment_failed processing', [
            'payment_intent_id' => $paymentIntent->id,
            'booking_id' => $booking->id,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);

        $booking->increment('charge_attempt_count');
        $booking->update([
            'payment_status' => 'failed',
            'last_charge_attempt_at' => now(),
        ]);

        $clientPayment = ClientPayment::where('booking_id', $bookingId)
            ->where('status', 'pending')
            ->first();

        if ($clientPayment) {
            $clientPayment->update([
                'status' => 'failed',
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);

            Log::info('Stripe webhook: client payment marked as failed', [
                'payment_intent_id' => $paymentIntent->id,
                'client_payment_id' => $clientPayment->id,
            ]);
        } else {
            Log::info('Stripe webhook: no pending client payment to mark as failed', [
                'payment_intent_id' => $paymentIntent->id,
                'booking_id' => $booking->id,
            ]);
        }

        app(PaymentFailureHandler::class)->handle($booking, $errorCode, $errorMessage);
    }
}
