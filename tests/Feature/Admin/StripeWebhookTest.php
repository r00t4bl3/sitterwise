<?php

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use App\Models\PricingRule;
use App\Services\Webhooks\StripeWebhookHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function completedBooking(): Booking
{
    $client = Client::factory()->create([
        'stripe_customer_id' => 'cus_'.uniqid(),
    ]);

    PricingRule::create([
        'service_type' => ServiceType::Babysitter->value,
        'number_of_children' => 0,
        'is_for_pets' => false,
        'charge_to_client' => 20,
        'paid_to_caregiver' => 15,
        'sitterwise_cut' => 5,
        'payment_form' => 'Stripe',
    ]);

    return Booking::factory()->forClient($client)->create([
        'status' => BookingStatus::Completed->value,
        'charge_to_client_hourly' => 20,
        'total_working_hour' => 5,
        'charge_to_client' => 100,
        'total_service_amount' => 100,
        'total_amount' => 100,
        'reimbursement' => 0,
        'bonus' => 0,
        'tip' => 0,
        'payment_status' => BookingPaymentStatus::Pending->value,
        'charge_attempt_count' => 0,
    ]);
}

describe('Stripe Webhook', function () {
    test('payment_intent.succeeded updates booking payment status and actual amount', function () {
        $booking = completedBooking();
        $paymentIntentId = 'pi_'.uniqid();

        $paymentIntent = (object) [
            'id' => $paymentIntentId,
            'amount' => 10000,
            'metadata' => (object) ['booking_id' => $booking->id],
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handlePaymentIntentSucceeded');
        $ref->setAccessible(true);
        $ref->invoke($handler, $paymentIntent);

        $booking->refresh();
        expect($booking->payment_status)->toBe('charged');
        expect($booking->stripe_payment_intent_id)->toBe($paymentIntentId);
        expect((float) $booking->actual_amount)->toBe(100.0);
    });

    test('payment_intent.succeeded links to existing pending ClientPayment', function () {
        $booking = completedBooking();
        $paymentIntentId = 'pi_'.uniqid();

        ClientPayment::create([
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'payment_method_id' => ClientPaymentMethod::factory()->create(['client_id' => $booking->client_id])->id,
            'amount' => 100,
            'currency' => 'usd',
            'status' => 'pending',
            'provider' => 'stripe',
            'provider_payment_id' => null,
        ]);

        $paymentIntent = (object) [
            'id' => $paymentIntentId,
            'amount' => 10000,
            'metadata' => (object) ['booking_id' => $booking->id],
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handlePaymentIntentSucceeded');
        $ref->setAccessible(true);
        $ref->invoke($handler, $paymentIntent);

        $payment = ClientPayment::where('booking_id', $booking->id)->first();
        expect($payment->status)->toBe('captured');
        expect($payment->provider_payment_id)->toBe($paymentIntentId);
    });

    test('payment_intent.failed increments charge_attempt_count and sets failed status', function () {
        Notification::fake();
        Mail::fake();

        $booking = completedBooking();
        $paymentIntentId = 'pi_'.uniqid();

        $paymentIntent = (object) [
            'id' => $paymentIntentId,
            'metadata' => (object) ['booking_id' => $booking->id],
            'last_payment_error' => (object) [
                'code' => 'card_declined',
                'message' => 'Your card was declined',
            ],
        ];

        $handler = app(StripeWebhookHandler::class);

        $ref = new ReflectionMethod($handler, 'handlePaymentIntentFailed');
        $ref->setAccessible(true);
        $ref->invoke($handler, $paymentIntent);

        $booking->refresh();
        expect($booking->charge_attempt_count)->toBe(1);
        expect($booking->payment_status)->toBe('failed');
    });

    test('missing booking does not throw exception on succeeded', function () {
        $handler = new StripeWebhookHandler;

        $paymentIntent = (object) [
            'id' => 'pi_'.uniqid(),
            'amount' => 1000,
            'metadata' => (object) ['booking_id' => 999999],
        ];

        $ref = new ReflectionMethod($handler, 'handlePaymentIntentSucceeded');
        $ref->setAccessible(true);
        $ref->invoke($handler, $paymentIntent);

        expect(true)->toBeTrue();
    });

    test('missing booking does not throw exception on failed', function () {
        $handler = new StripeWebhookHandler;

        $paymentIntent = (object) [
            'id' => 'pi_'.uniqid(),
            'metadata' => (object) ['booking_id' => 999999],
            'last_payment_error' => (object) [
                'code' => 'generic_decline',
                'message' => 'Declined',
            ],
        ];

        $ref = new ReflectionMethod($handler, 'handlePaymentIntentFailed');
        $ref->setAccessible(true);
        $ref->invoke($handler, $paymentIntent);

        expect(true)->toBeTrue();
    });

    test('invalid signature returns failure', function () {
        $booking = completedBooking();

        $handler = new StripeWebhookHandler;

        $result = $handler->handle([
            'id' => 'evt_'.uniqid(),
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_'.uniqid(),
                    'amount' => 1000,
                    'metadata' => [
                        'booking_id' => $booking->id,
                    ],
                ],
            ],
        ], 'completely-wrong-signature');

        expect($result['success'])->toBeFalse();
    });

    test('POST /webhooks/stripe returns 200 with valid signature', function () {
        Config::set('services.stripe.webhook_secret', 'whsec_test');

        $booking = completedBooking();
        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_'.uniqid(),
                    'amount' => 10000,
                    'metadata' => [
                        'booking_id' => $booking->id,
                    ],
                ],
            ],
        ];

        $jsonPayload = json_encode($payload);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$jsonPayload}", 'whsec_test');

        $response = $this->postJson('/webhooks/stripe', $payload, [
            'Stripe-Signature' => "t={$timestamp},v1={$signature}",
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    });

    test('POST /webhooks/stripe returns failure with invalid signature', function () {
        Config::set('services.stripe.webhook_secret', 'whsec_test');

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_'.uniqid(),
                    'amount' => 1000,
                    'metadata' => [],
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/stripe', $payload, [
            'Stripe-Signature' => 't='.time().',v1=garbage_signature',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => false]);
    });
});
