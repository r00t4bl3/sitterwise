<?php

namespace App\Services\Webhooks;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CaregiverPayout;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use App\Services\Billing\PaymentFailureHandler;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeWebhookHandler
{
    public function handle(string $payload, string $signature): array
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
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

        try {
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;

                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event->data->object);
                    break;

                case 'charge.dispute.created':
                    $this->handleDisputeCreated($event->data->object);
                    break;

                case 'setup_intent.succeeded':
                    $this->handleSetupIntentSucceeded($event->data->object);
                    break;

                case 'setup_intent.setup_failed':
                    $this->handleSetupIntentFailed($event->data->object);
                    break;

                case 'payment_method.attached':
                    $this->handlePaymentMethodAttached($event->data->object);
                    break;

                case 'payment_method.detached':
                    $this->handlePaymentMethodDetached($event->data->object);
                    break;

                case 'transfer.created':
                    $this->handleTransferCreated($event->data->object);
                    break;

                case 'transfer.reversed':
                    $this->handleTransferReversed($event->data->object);
                    break;

                case 'charge.refunded':
                    $this->handleChargeRefunded($event->data->object);
                    break;

                default:
                    Log::warning('Stripe webhook: unhandled event type', [
                        'type' => $event->type,
                        'id' => $event->id,
                    ]);
            }
        } catch (\Throwable $e) {
            Log::error('Stripe webhook: handler exception', [
                'type' => $event->type,
                'id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
            'status' => BookingStatus::Paid->value,
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
                'status' => 'succeeded',
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

    protected function handleCheckoutSessionCompleted($session): void
    {
        if (($session->mode ?? '') !== 'setup') {
            return;
        }

        $setupIntentId = $session->setup_intent ?? null;

        if (! $setupIntentId) {
            Log::warning('Stripe webhook: checkout.session.completed missing setup_intent', [
                'session_id' => $session->id,
            ]);

            return;
        }

        $customerId = $session->customer ?? null;

        if (! $customerId) {
            Log::warning('Stripe webhook: checkout.session.completed missing customer', [
                'session_id' => $session->id,
            ]);

            return;
        }

        $client = Client::where('stripe_customer_id', $customerId)->first();

        if (! $client) {
            Log::info('Stripe webhook: checkout.session.completed no client found for customer', [
                'session_id' => $session->id,
                'customer_id' => $customerId,
            ]);

            return;
        }

        $this->syncPaymentMethod($client, $setupIntentId, "checkout.session.{$session->id}");

        Log::info('Stripe webhook: checkout.session.completed processed', [
            'session_id' => $session->id,
            'client_id' => $client->id,
        ]);
    }

    protected function handleDisputeCreated($dispute): void
    {
        $paymentIntentId = $dispute->payment_intent ?? null;

        Log::warning('Stripe webhook: charge.dispute.created', [
            'dispute_id' => $dispute->id,
            'payment_intent_id' => $paymentIntentId,
            'amount' => ($dispute->amount ?? 0) / 100,
            'reason' => $dispute->reason ?? 'unknown',
            'status' => $dispute->status ?? 'unknown',
        ]);

        if ($paymentIntentId) {
            $booking = Booking::where('stripe_payment_intent_id', $paymentIntentId)->first();

            if ($booking) {
                $booking->update(['payment_status' => 'disputed']);

                Log::warning('Stripe webhook: booking marked as disputed', [
                    'booking_id' => $booking->id,
                    'dispute_id' => $dispute->id,
                ]);
            }
        }
    }

    protected function handleSetupIntentSucceeded($setupIntent): void
    {
        $customerId = $setupIntent->customer ?? null;

        if (! $customerId) {
            Log::warning('Stripe webhook: setup_intent.succeeded missing customer', [
                'setup_intent_id' => $setupIntent->id,
            ]);

            return;
        }

        $client = Client::where('stripe_customer_id', $customerId)->first();

        if (! $client) {
            Log::info('Stripe webhook: setup_intent.succeeded no client found for customer', [
                'setup_intent_id' => $setupIntent->id,
                'customer_id' => $customerId,
            ]);

            return;
        }

        $this->syncPaymentMethod($client, $setupIntent->id, "setup_intent.{$setupIntent->id}");

        Log::info('Stripe webhook: setup_intent.succeeded processed', [
            'setup_intent_id' => $setupIntent->id,
            'client_id' => $client->id,
        ]);
    }

    protected function handleSetupIntentFailed($setupIntent): void
    {
        Log::warning('Stripe webhook: setup_intent.setup_failed', [
            'setup_intent_id' => $setupIntent->id,
            'customer_id' => $setupIntent->customer ?? null,
            'error_code' => $setupIntent->last_setup_error?->code ?? 'unknown',
            'error_message' => $setupIntent->last_setup_error?->message ?? 'Setup failed',
        ]);
    }

    protected function handlePaymentMethodAttached($paymentMethod): void
    {
        $customerId = $paymentMethod->customer ?? null;

        if (! $customerId) {
            return;
        }

        $client = Client::where('stripe_customer_id', $customerId)->first();

        if (! $client) {
            return;
        }

        $providerMethodId = $paymentMethod->id ?? null;

        if (! $providerMethodId) {
            return;
        }

        $card = $paymentMethod->card ?? null;

        $brand = $card->brand ?? null;
        $last4 = $card->last4 ?? null;
        $expMonth = $card->exp_month ?? null;
        $expYear = $card->exp_year ?? null;

        $existing = ClientPaymentMethod::where('provider_method_id', $providerMethodId)->first();

        if ($existing) {
            $existing->update([
                'status' => 'active',
                'brand' => $brand ?? $existing->brand,
                'last4' => $last4 ?? $existing->last4,
                'exp_month' => $expMonth ?? $existing->exp_month,
                'exp_year' => $expYear ?? $existing->exp_year,
            ]);
        } else {
            ClientPaymentMethod::create([
                'client_id' => $client->id,
                'provider' => 'stripe',
                'provider_method_id' => $providerMethodId,
                'brand' => $brand,
                'last4' => $last4,
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'status' => 'active',
            ]);
        }

        Log::info('Stripe webhook: payment_method.attached processed', [
            'client_id' => $client->id,
            'provider_method_id' => $providerMethodId,
        ]);
    }

    protected function handlePaymentMethodDetached($paymentMethod): void
    {
        $providerMethodId = $paymentMethod->id ?? null;

        if (! $providerMethodId) {
            return;
        }

        $method = ClientPaymentMethod::where('provider_method_id', $providerMethodId)->first();

        if ($method) {
            $method->update(['status' => 'inactive']);

            Log::info('Stripe webhook: payment_method.detached processed', [
                'client_id' => $method->client_id,
                'provider_method_id' => $providerMethodId,
            ]);
        }
    }

    protected function handleTransferCreated($transfer): void
    {
        $payout = CaregiverPayout::where('provider_transfer_id', $transfer->id)->first();

        Log::info('Stripe webhook: transfer.created', [
            'transfer_id' => $transfer->id,
            'amount' => ($transfer->amount ?? 0) / 100,
            'destination' => $transfer->destination ?? null,
        ]);

        if ($payout) {
            $payout->update(['status' => 'processing']);

            Log::info('Stripe webhook: caregiver payout marked as processing', [
                'payout_id' => $payout->id,
                'transfer_id' => $transfer->id,
                'caregiver_id' => $payout->caregiver_id,
            ]);
        } else {
            Log::info('Stripe webhook: transfer.created no matching payout found', [
                'transfer_id' => $transfer->id,
                'amount' => ($transfer->amount ?? 0) / 100,
                'currency' => $transfer->currency ?? null,
                'destination' => $transfer->destination ?? null,
                'description' => $transfer->description ?? null,
                'created' => $transfer->created ?? null,
                'metadata' => $transfer->metadata ?? [],
            ]);
        }
    }

    protected function handleTransferReversed($transfer): void
    {
        $payout = CaregiverPayout::where('provider_transfer_id', $transfer->id)->first();

        Log::warning('Stripe webhook: transfer.reversed', [
            'transfer_id' => $transfer->id,
            'amount' => ($transfer->amount ?? 0) / 100,
            'reversal_amount' => ($transfer->amount_reversed ?? 0) / 100,
        ]);

        if ($payout) {
            $payout->update(['status' => 'reversed']);

            Log::warning('Stripe webhook: caregiver payout marked as reversed', [
                'payout_id' => $payout->id,
                'transfer_id' => $transfer->id,
                'caregiver_id' => $payout->caregiver_id,
            ]);
        }
    }

    protected function handleChargeRefunded($charge): void
    {
        $paymentIntentId = $charge->payment_intent ?? null;

        Log::info('Stripe webhook: charge.refunded', [
            'charge_id' => $charge->id,
            'payment_intent_id' => $paymentIntentId,
            'amount_refunded' => ($charge->amount_refunded ?? 0) / 100,
            'refund_reason' => $charge->refunds?->data[0]->reason ?? 'unknown',
        ]);

        if ($paymentIntentId) {
            $booking = Booking::where('stripe_payment_intent_id', $paymentIntentId)->first();

            if ($booking) {
                $booking->update(['payment_status' => 'refunded']);

                Log::info('Stripe webhook: booking marked as refunded', [
                    'booking_id' => $booking->id,
                    'charge_id' => $charge->id,
                ]);
            }
        }
    }

    protected function syncPaymentMethod(Client $client, string $setupIntentId, string $source): void
    {
        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $setupIntent = $stripe->setupIntents->retrieve($setupIntentId);
        } catch (\Exception $e) {
            Log::warning('Stripe webhook: failed to retrieve setup intent for sync', [
                'setup_intent_id' => $setupIntentId,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $paymentMethodId = $setupIntent->payment_method ?? null;

        if (! $paymentMethodId) {
            return;
        }

        try {
            $pm = $stripe->paymentMethods->retrieve($paymentMethodId);
        } catch (\Exception $e) {
            Log::warning('Stripe webhook: failed to retrieve payment method for sync', [
                'payment_method_id' => $paymentMethodId,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $card = $pm->card ?? null;

        $brand = $card->brand ?? null;
        $last4 = $card->last4 ?? null;
        $expMonth = $card->exp_month ?? null;
        $expYear = $card->exp_year ?? null;

        $existing = ClientPaymentMethod::where('provider_method_id', $paymentMethodId)->first();

        if ($existing) {
            $existing->update([
                'status' => 'active',
                'brand' => $brand ?? $existing->brand,
                'last4' => $last4 ?? $existing->last4,
                'exp_month' => $expMonth ?? $existing->exp_month,
                'exp_year' => $expYear ?? $existing->exp_year,
            ]);
        } else {
            ClientPaymentMethod::create([
                'client_id' => $client->id,
                'provider' => 'stripe',
                'provider_method_id' => $paymentMethodId,
                'brand' => $brand,
                'last4' => $last4,
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'status' => 'active',
            ]);
        }

        Log::info('Stripe webhook: payment method synced', [
            'client_id' => $client->id,
            'provider_method_id' => $paymentMethodId,
            'source' => $source,
        ]);
    }
}
