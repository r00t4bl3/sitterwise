<?php

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\CaregiverPayout;
use App\Models\CaregiverPayoutMethod;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use App\Models\PricingRule;
use App\Models\User;
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

    test('checkout.session.completed skips non-setup mode sessions', function () {
        $session = (object) [
            'id' => 'cs_'.uniqid(),
            'mode' => 'payment',
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handleCheckoutSessionCompleted');
        $ref->setAccessible(true);
        $ref->invoke($handler, $session);

        expect(true)->toBeTrue();
    });

    test('checkout.session.completed skips sessions without setup_intent', function () {
        $session = (object) [
            'id' => 'cs_'.uniqid(),
            'mode' => 'setup',
            'setup_intent' => null,
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handleCheckoutSessionCompleted');
        $ref->setAccessible(true);
        $ref->invoke($handler, $session);

        expect(true)->toBeTrue();
    });

    test('checkout.session.completed skips sessions without customer', function () {
        $session = (object) [
            'id' => 'cs_'.uniqid(),
            'mode' => 'setup',
            'setup_intent' => 'seti_'.uniqid(),
            'customer' => null,
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handleCheckoutSessionCompleted');
        $ref->setAccessible(true);
        $ref->invoke($handler, $session);

        expect(true)->toBeTrue();
    });

    test('charge.dispute.created marks booking disputed', function () {
        $booking = completedBooking();
        $paymentIntentId = 'pi_'.uniqid();
        $booking->update(['stripe_payment_intent_id' => $paymentIntentId]);

        $dispute = (object) [
            'id' => 'dp_'.uniqid(),
            'payment_intent' => $paymentIntentId,
            'amount' => 10000,
            'reason' => 'fraudulent',
            'status' => 'needs_response',
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handleDisputeCreated');
        $ref->setAccessible(true);
        $ref->invoke($handler, $dispute);

        $booking->refresh();
        expect($booking->payment_status)->toBe('disputed');
    });

    test('charge.dispute.created does not throw when booking not found', function () {
        $dispute = (object) [
            'id' => 'dp_'.uniqid(),
            'payment_intent' => 'pi_'.uniqid(),
            'amount' => 10000,
            'reason' => 'fraudulent',
            'status' => 'needs_response',
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handleDisputeCreated');
        $ref->setAccessible(true);
        $ref->invoke($handler, $dispute);

        expect(true)->toBeTrue();
    });

    test('setup_intent.succeeded skips events without customer', function () {
        $setupIntent = (object) [
            'id' => 'seti_'.uniqid(),
            'customer' => null,
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handleSetupIntentSucceeded');
        $ref->setAccessible(true);
        $ref->invoke($handler, $setupIntent);

        expect(true)->toBeTrue();
    });

    test('setup_intent.setup_failed logs without exception', function () {
        $setupIntent = (object) [
            'id' => 'seti_'.uniqid(),
            'customer' => 'cus_'.uniqid(),
            'last_setup_error' => (object) [
                'code' => 'authentication_required',
                'message' => 'Your card was not authenticated',
            ],
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handleSetupIntentFailed');
        $ref->setAccessible(true);
        $ref->invoke($handler, $setupIntent);

        expect(true)->toBeTrue();
    });

    test('payment_method.attached creates ClientPaymentMethod', function () {
        $client = Client::factory()->create([
            'stripe_customer_id' => 'cus_'.uniqid(),
        ]);

        $pmId = 'pm_'.uniqid();
        $paymentMethod = (object) [
            'id' => $pmId,
            'customer' => $client->stripe_customer_id,
            'card' => (object) [
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2027,
            ],
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handlePaymentMethodAttached');
        $ref->setAccessible(true);
        $ref->invoke($handler, $paymentMethod);

        $method = ClientPaymentMethod::where('provider_method_id', $pmId)->first();
        expect($method)->not->toBeNull();
        expect($method->client_id)->toBe($client->id);
        expect($method->provider)->toBe('stripe');
        expect($method->brand)->toBe('visa');
        expect($method->last4)->toBe('4242');
        expect($method->status)->toBe('active');
    });

    test('payment_method.attached reactivates existing method', function () {
        $client = Client::factory()->create([
            'stripe_customer_id' => 'cus_'.uniqid(),
        ]);

        $pmId = 'pm_'.uniqid();
        $existing = ClientPaymentMethod::create([
            'client_id' => $client->id,
            'provider' => 'stripe',
            'provider_method_id' => $pmId,
            'brand' => 'mastercard',
            'last4' => '1111',
            'exp_month' => 1,
            'exp_year' => 2026,
            'status' => 'inactive',
        ]);

        $paymentMethod = (object) [
            'id' => $pmId,
            'customer' => $client->stripe_customer_id,
            'card' => (object) [
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2027,
            ],
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handlePaymentMethodAttached');
        $ref->setAccessible(true);
        $ref->invoke($handler, $paymentMethod);

        $existing->refresh();
        expect($existing->status)->toBe('active');
        expect($existing->brand)->toBe('visa');
        expect($existing->last4)->toBe('4242');
    });

    test('payment_method.detached marks method inactive', function () {
        $client = Client::factory()->create();
        $pmId = 'pm_'.uniqid();

        ClientPaymentMethod::create([
            'client_id' => $client->id,
            'provider' => 'stripe',
            'provider_method_id' => $pmId,
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2027,
            'status' => 'active',
        ]);

        $paymentMethod = (object) [
            'id' => $pmId,
            'customer' => $client->stripe_customer_id,
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handlePaymentMethodDetached');
        $ref->setAccessible(true);
        $ref->invoke($handler, $paymentMethod);

        $method = ClientPaymentMethod::where('provider_method_id', $pmId)->first();
        expect($method->status)->toBe('inactive');
    });

    test('transfer.reversed marks CaregiverPayout as reversed', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Caregiver',
            'slug' => 'test-caregiver-'.uniqid(),
            'status' => 'active',
        ]);
        $payoutMethod = CaregiverPayoutMethod::factory()->create([
            'caregiver_id' => $caregiver->id,
        ]);

        $payout = CaregiverPayout::factory()->create([
            'caregiver_id' => $caregiver->id,
            'caregiver_payout_method_id' => $payoutMethod->id,
            'provider_transfer_id' => 'tr_'.uniqid(),
            'status' => 'completed',
        ]);

        $transfer = (object) [
            'id' => $payout->provider_transfer_id,
            'amount' => 10000,
            'amount_reversed' => 5000,
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handleTransferReversed');
        $ref->setAccessible(true);
        $ref->invoke($handler, $transfer);

        $payout->refresh();
        expect($payout->status)->toBe('reversed');
    });

    test('charge.refunded marks booking as refunded', function () {
        $booking = completedBooking();
        $paymentIntentId = 'pi_'.uniqid();
        $booking->update(['stripe_payment_intent_id' => $paymentIntentId]);

        $charge = (object) [
            'id' => 'ch_'.uniqid(),
            'payment_intent' => $paymentIntentId,
            'amount_refunded' => 10000,
            'refunds' => (object) [
                'data' => [
                    (object) ['reason' => 'requested_by_customer'],
                ],
            ],
        ];

        $handler = new StripeWebhookHandler;
        $ref = new ReflectionMethod($handler, 'handleChargeRefunded');
        $ref->setAccessible(true);
        $ref->invoke($handler, $charge);

        $booking->refresh();
        expect($booking->payment_status)->toBe('refunded');
    });

    test('handler exception returns success true', function () {
        $booking = completedBooking();
        $paymentIntentId = 'pi_'.uniqid();

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'amount' => 10000,
                    'metadata' => [
                        'booking_id' => $booking->id,
                    ],
                ],
            ],
        ];

        $jsonPayload = json_encode($payload);
        $timestamp = time();
        Config::set('services.stripe.webhook_secret', 'whsec_test');
        $signature = hash_hmac('sha256', "{$timestamp}.{$jsonPayload}", 'whsec_test');

        $mock = Mockery::mock(StripeWebhookHandler::class)->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('handlePaymentIntentSucceeded')->andThrow(new RuntimeException('DB connection lost'));

        $result = $mock->handle($payload, "t={$timestamp},v1={$signature}");

        expect($result['success'])->toBeTrue();
    });
});
