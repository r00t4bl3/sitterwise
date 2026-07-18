<?php

namespace App\Services\Billing;

use App\Models\Booking;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use App\Notifications\CaregiverTipReceivedNotification;
use App\Services\CaregiverPayout\CaregiverPayoutService;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;

class TipChargeService
{
    protected StripeClient $stripe;

    public function __construct(?StripeClient $stripe = null)
    {
        $this->stripe = $stripe ?? new StripeClient(config('services.stripe.secret'));
    }

    /**
     * The Stripe charge description for a tip. Names the caregiver (for easy
     * payroll reconciliation) rather than exposing our internal job number.
     */
    public function tipDescription(Booking $booking): string
    {
        return $booking->caregiver
            ? "Tip for {$booking->caregiver->full_name}"
            : "Booking #{$booking->id} - Tip for Caregiver";
    }

    public function charge(Booking $booking, float $tipAmount, ?string $paymentMethodId = null): array
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

        // A booking can only be tipped once. Editing a review re-submits the
        // (pre-filled) tip, so without this guard a succeeded tip would be
        // charged again. Treat an already-succeeded tip as a no-op success so
        // the review edit completes cleanly rather than surfacing a failure.
        $existingSucceeded = ClientPayment::where('booking_id', $booking->id)
            ->where('status', 'succeeded')
            ->whereJsonContains('metadata->type', 'tip')
            ->exists();

        if ($existingSucceeded) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'Tip already processed for this booking',
            ];
        }

        if ($tipAmount <= 0) {
            return ['success' => false, 'message' => 'Tip amount must be greater than 0'];
        }

        $client = $booking->client;

        if (! $client->stripe_customer_id) {
            $stripeCustomer = $this->stripe->customers->create([
                'email' => $client->email,
                'metadata' => [
                    'client_id' => $client->id,
                ],
            ]);
            $client->update(['stripe_customer_id' => $stripeCustomer->id]);
        }

        $paymentMethod = null;

        Log::info('TipChargeService', [
            'paymentMethodId' => $paymentMethodId,
            'client_id' => $client->id,
        ]);

        if ($paymentMethodId) {
            $paymentMethod = ClientPaymentMethod::where('client_id', $client->id)
                ->where('provider_method_id', $paymentMethodId)
                ->where('status', 'active')
                ->first();

            Log::info('TipChargeService: Existing payment method lookup', [
                'found' => (bool) $paymentMethod,
            ]);

            if (! $paymentMethod) {
                $paymentMethod = $this->createPaymentMethodFromStripe($client, $paymentMethodId);
                Log::info('TipChargeService: Created from Stripe', [
                    'result' => $paymentMethod ? 'success' : 'failed',
                ]);
            }
        }

        if (! $paymentMethod) {
            $paymentMethod = ClientPaymentMethod::where('client_id', $client->id)
                ->where('is_default', true)
                ->where('status', 'active')
                ->first();
        }

        if (! $paymentMethod) {
            Log::warning('TipChargeService: No payment method found', [
                'client_id' => $client->id,
                'paymentMethodId_provided' => ! empty($paymentMethodId),
            ]);

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
                'description' => $this->tipDescription($booking),
                'metadata' => [
                    'booking_id' => $booking->id,
                    'caregiver_name' => $booking->caregiver?->full_name,
                    'type' => 'tip',
                ],
            ], [
                // Defense-in-depth alongside the pending/succeeded guards
                // above: if a request is retried after a network failure,
                // Stripe collapses it into the original charge.
                'idempotency_key' => "booking_{$booking->id}_tip_{$amountInCents}",
            ]);

            $booking->update(['tip' => $tipAmount]);

            $clientPayment->update([
                'status' => 'succeeded',
                'provider_payment_id' => $paymentIntent->id,
                'paid_at' => now(),
            ]);

            // Let the caregiver know a tip landed — otherwise they only see it by
            // passively opening their Jobs pages. In-app (database) only.
            $caregiverUser = $booking->caregiver?->user;
            if ($caregiverUser) {
                $caregiverUser->notify(new CaregiverTipReceivedNotification($booking, $tipAmount));
            }

            if (config('services.stripe.enable_caregiver_transfers')) {
                try {
                    app(CaregiverPayoutService::class)->transferFunds(
                        $booking->caregiver,
                        $amountInCents,
                        idempotencyKey: "booking_{$booking->id}_tip_payout_{$amountInCents}"
                    );
                } catch (\Exception $e) {
                    Log::warning('Tip charged but caregiver transfer failed', [
                        'booking_id' => $booking->id,
                        'caregiver_id' => $booking->caregiver_id,
                        'amount' => $tipAmount,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

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

    protected function createPaymentMethodFromStripe($client, string $paymentMethodId): ?ClientPaymentMethod
    {
        try {
            Log::info('createPaymentMethodFromStripe: Starting', [
                'client_id' => $client->id,
                'stripe_customer_id' => $client->stripe_customer_id,
                'paymentMethodId' => $paymentMethodId,
            ]);

            $stripePaymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $client->stripe_customer_id,
            ]);

            Log::info('createPaymentMethodFromStripe: Attached to customer');

            Log::info('createPaymentMethodFromStripe: About to store ClientPaymentMethod');

            // The attach above fires a payment_method.attached webhook that also
            // stores the row; upsert on the unique provider_method_id so a blind
            // create can't hit a duplicate-key error. Preserve an existing default.
            $existing = ClientPaymentMethod::where('provider_method_id', $paymentMethodId)->first();

            $paymentMethod = ClientPaymentMethod::updateOrCreate(
                ['provider_method_id' => $paymentMethodId],
                [
                    'client_id' => $client->id,
                    'provider' => 'stripe',
                    'brand' => $stripePaymentMethod->card?->brand ?? 'unknown',
                    'last4' => $stripePaymentMethod->card?->last4 ?? '****',
                    'exp_month' => $stripePaymentMethod->card?->exp_month ?? 1,
                    'exp_year' => $stripePaymentMethod->card?->exp_year ?? 2025,
                    'is_default' => $existing?->is_default ?? false,
                    'status' => 'active',
                ]
            );

            Log::info('createPaymentMethodFromStripe: Created ClientPaymentMethod', ['id' => $paymentMethod->id]);

            return $paymentMethod;
        } catch (\Exception $e) {
            Log::error('createPaymentMethodFromStripe: Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
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
