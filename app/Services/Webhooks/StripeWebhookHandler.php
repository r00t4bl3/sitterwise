<?php

namespace App\Services\Webhooks;

use App\Models\Booking;
use App\Models\ClientPayment;
use App\Services\Billing\PaymentFailureHandler;
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
            return [
                'success' => false,
                'message' => 'Invalid signature',
            ];
        } catch (\UnexpectedValueException $e) {
            return [
                'success' => false,
                'message' => 'Invalid payload',
            ];
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event->data->object);
                break;
        }

        return [
            'success' => true,
        ];
    }

    protected function handlePaymentIntentSucceeded($paymentIntent): void
    {
        $bookingId = $paymentIntent->metadata->booking_id ?? null;

        if ($bookingId) {
            $booking = Booking::find($bookingId);

            if ($booking) {
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
                }
            }
        }
    }

    protected function handlePaymentIntentFailed($paymentIntent): void
    {
        $bookingId = $paymentIntent->metadata->booking_id ?? null;

        if ($bookingId) {
            $booking = Booking::find($bookingId);

            if ($booking) {
                $booking->increment('charge_attempt_count');
                $booking->update([
                    'payment_status' => 'failed',
                    'last_charge_attempt_at' => now(),
                ]);

                $clientPayment = ClientPayment::where('booking_id', $bookingId)
                    ->where('status', 'pending')
                    ->first();

                $errorCode = $paymentIntent->last_payment_error->code ?? 'unknown';
                $errorMessage = $paymentIntent->last_payment_error->message ?? 'Payment failed';

                if ($clientPayment) {
                    $clientPayment->update([
                        'status' => 'failed',
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage,
                    ]);
                }

                app(PaymentFailureHandler::class)->handle($booking, $errorCode, $errorMessage);
            }
        }
    }
}
